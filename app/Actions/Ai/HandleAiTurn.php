<?php

namespace App\Actions\Ai;

use App\Actions\Messaging\SendMessage;
use App\Domain\Ai\Contracts\AiTool;
use App\Domain\Ai\Contracts\AudioTranscriber;
use App\Domain\Ai\DataObjects\AiMessage;
use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiResponse;
use App\Domain\Ai\Exceptions\AiException;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Ai\AiHandoffRequested;
use App\Models\AiInteraction;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Services\Ai\AiCostCalculator;
use App\Services\Ai\AiManager;
use App\Services\Ai\ToolRegistry;

class HandleAiTurn
{
    private const MAX_TOOL_ROUNDS = 4;

    private const HISTORY_SIZE = 30;

    private const ACTION_REMINDER = 'Sua última mensagem prometeu uma ação ao contato, mas nenhuma ferramenta foi chamada, então nada aconteceu de fato. Se a ação é necessária, chame a ferramenta agora. Não escreva outra mensagem: o contato já recebeu a anterior e qualquer texto seu neste momento será descartado. Se nenhuma ação era necessária, responda apenas "ok".';

    private const ACTION_PROMISE = '/\b(?:vou|irei|vamos)\s+(?:te\s+|lhe\s+)?(?:transferir|encaminhar|passar|repassar|direcionar|chamar|acionar|encerrar|finalizar|registrar|anotar|marcar|fazer)\b|\bum\s+momento\b|\bum\s+instante\b|\bagora\s+mesmo\b|\baguarde\b|\bestou\s+(?:transferindo|encaminhando|passando|encerrando|finalizando|registrando|acionando)\b/iu';

    public function __construct(
        private readonly AiManager $ai,
        private readonly ToolRegistry $tools,
        private readonly SendMessage $sendMessage,
        private readonly AiCostCalculator $costs,
        private readonly AudioTranscriber $transcriber,
    ) {}

    public function handle(Conversation $conversation): void
    {
        $objective = $conversation->aiObjective;

        if ($objective === null || ! $objective->is_active) {
            return;
        }

        if (! $this->canContinue($conversation, $objective)) {
            AiHandoffRequested::dispatch($conversation, $objective, 'Limite do objetivo atingido.');

            return;
        }

        $tools = $this->tools->resolve($objective->tools ?? []);
        $messages = $this->history($conversation);
        $reminded = false;
        $silent = false;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->ask($conversation, $objective, $messages, $tools);

            if (! $silent) {
                $this->reply($conversation, $response);
            }

            $silent = false;

            if (! $response->hasToolCalls()) {
                if ($reminded || $tools === [] || ! $this->promisedAction($response)) {
                    return;
                }

                $reminded = true;
                $silent = true;

                $messages[] = AiMessage::assistant((string) $response->content);
                $messages[] = AiMessage::user(self::ACTION_REMINDER);

                continue;
            }

            $messages[] = AiMessage::assistant($response->content ?? '', $response->toolCalls);

            foreach ($response->toolCalls as $call) {
                $tool = $this->tools->find($call->name);

                $messages[] = AiMessage::tool(
                    $call->id,
                    $call->name,
                    $tool?->execute($conversation->refresh(), $call->arguments) ?? 'Ferramenta indisponível.',
                );
            }

            if ($conversation->refresh()->aiObjective === null) {
                return;
            }
        }
    }

    /**
     * @param  array<int, AiMessage>  $messages
     * @param  array<int, AiTool>  $tools
     */
    private function ask(Conversation $conversation, AiObjective $objective, array $messages, array $tools): AiResponse
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->ai->provider($objective->provider)->chat(new AiRequest(
                model: $objective->model,
                systemPrompt: $this->systemPrompt($conversation, $objective),
                messages: $messages,
                temperature: $objective->temperature,
                maxTokens: $objective->max_tokens,
                tools: $tools,
            ));
        } catch (AiException $exception) {
            $this->record($conversation, $objective, 0, 0, $startedAt, 'failed', $exception->getMessage());

            throw $exception;
        }

        $this->record($conversation, $objective, $response->inputTokens, $response->outputTokens, $startedAt);

        return $response;
    }

    private function reply(Conversation $conversation, AiResponse $response): void
    {
        if (blank($response->content)) {
            return;
        }

        $this->sendMessage->handle(
            conversation: $conversation,
            body: $response->content,
            source: MessageSource::Ai,
        );
    }

    private function promisedAction(AiResponse $response): bool
    {
        return filled($response->content)
            && preg_match(self::ACTION_PROMISE, (string) $response->content) === 1;
    }

    /**
     * @return array<int, AiMessage>
     */
    private function history(Conversation $conversation): array
    {
        return $conversation->messages()
            ->where('is_internal', false)
            ->latest('id')
            ->limit(self::HISTORY_SIZE)
            ->get()
            ->reverse()
            ->map(fn (Message $message): AiMessage => new AiMessage(
                role: $message->isInbound() ? 'user' : 'assistant',
                content: $this->readable($message),
            ))
            ->values()
            ->all();
    }

    private function readable(Message $message): string
    {
        if (filled($message->body)) {
            return (string) $message->body;
        }

        $transcription = $this->transcription($message);

        return $transcription === null
            ? $message->type->label()
            : "[áudio transcrito] {$transcription}";
    }

    private function transcription(Message $message): ?string
    {
        if (filled($message->transcription)) {
            return $message->transcription;
        }

        if ($message->type !== MessageType::Audio || blank($message->media_url)) {
            return null;
        }

        if (! config('ai.transcription.enabled')) {
            return null;
        }

        $text = $this->transcriber->transcribe($message->media_url, $message->media_mime_type);

        if ($text !== null) {
            $message->forceFill(['transcription' => $text])->save();
        }

        return $text;
    }

    private function systemPrompt(Conversation $conversation, AiObjective $objective): string
    {
        $contact = $conversation->contact;

        return implode("\n\n", array_filter([
            $objective->system_prompt,
            'Quando for usar uma ferramenta, envie a frase para o contato e a chamada da ferramenta na mesma resposta. Uma resposta só de texto não executa nada: se você avisar que vai transferir, encerrar ou registrar e não chamar a ferramenta junto, nada acontece e o contato fica esperando por algo que nunca vem. Nunca prometa fazer depois, nunca peça um momento antes de agir e nunca narre na mensagem que está acionando ferramentas.',
            'Você só enxerga texto. Áudios chegam prontos, marcados com [áudio transcrito]: trate o conteúdo como se o contato tivesse escrito. Imagens, vídeos e documentos chegam apenas pelo nome do tipo, então nunca prometa analisá-los — peça ao contato que escreva o que precisa.',
            "Canal do atendimento: {$conversation->channel->label()}.",
            "Nome do contato: {$contact->name}.",
            $this->queueCatalog($objective),
            $this->handoffNote($objective),
            $this->tagCatalog($objective),
            $objective->closing_condition
                ? "Condição de encerramento: {$objective->closing_condition}"
                : null,
        ]));
    }

    private function queueCatalog(AiObjective $objective): ?string
    {
        if (! $this->uses($objective, 'transfer_to_queue')) {
            return null;
        }

        $queues = ServiceQueue::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ServiceQueue $queue): string => trim(
                "- {$queue->slug}: {$queue->name}".(filled($queue->description) ? " — {$queue->description}" : '')
            ));

        if ($queues->isEmpty()) {
            return 'Não há setores disponíveis para transferência. Resolva o atendimento você mesmo ou encaminhe para um atendente.';
        }

        return "Setores disponíveis para transferência. Use o identificador exato antes dos dois pontos no campo queue_slug de transfer_to_queue:\n"
            .$queues->implode("\n");
    }

    private function handoffNote(AiObjective $objective): ?string
    {
        if (! $this->uses($objective, 'request_human')) {
            return null;
        }

        $queue = $objective->handoffServiceQueue;

        return $queue === null
            ? 'request_human coloca o atendimento na fila de espera geral.'
            : "request_human coloca o atendimento no setor {$queue->name}. Para qualquer outro setor use transfer_to_queue.";
    }

    private function tagCatalog(AiObjective $objective): ?string
    {
        if (! $this->uses($objective, 'apply_tag')) {
            return null;
        }

        $tags = Tag::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): string => trim(
                "- {$tag->slug}: {$tag->name}".(filled($tag->description) ? " — {$tag->description}" : '')
            ));

        if ($tags->isEmpty()) {
            return 'Não há tags cadastradas. Não use apply_tag.';
        }

        return "Tags disponíveis. Use o identificador exato antes dos dois pontos no campo tag_slug de apply_tag, nunca invente um:\n"
            .$tags->implode("\n");
    }

    private function uses(AiObjective $objective, string $tool): bool
    {
        return in_array($tool, $objective->tools ?? [], true);
    }

    private function canContinue(Conversation $conversation, AiObjective $objective): bool
    {
        if (! $objective->hasBudgetLeft()) {
            return false;
        }

        $turns = AiInteraction::query()
            ->where('conversation_id', $conversation->id)
            ->where('ai_objective_id', $objective->id)
            ->count();

        return $turns < min($objective->max_turns, (int) config('ai.max_turns_per_conversation'));
    }

    private function record(
        Conversation $conversation,
        AiObjective $objective,
        int $inputTokens,
        int $outputTokens,
        float $startedAt,
        string $status = 'completed',
        ?string $error = null,
    ): void {
        AiInteraction::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'ai_objective_id' => $objective->id,
            'provider' => $objective->provider,
            'model' => $objective->model,
            'status' => $status,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $this->costs->cents($objective->model, $inputTokens, $outputTokens),
            'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'error' => $error,
        ]);
    }
}
