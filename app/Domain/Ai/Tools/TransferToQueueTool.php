<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Services\Conversations\ConversationRouter;
use Illuminate\Support\Collection;

class TransferToQueueTool implements AiTool
{
    public function __construct(private readonly ConversationRouter $router) {}

    public function name(): string
    {
        return 'transfer_to_queue';
    }

    public function label(): string
    {
        return 'Transferir para Setor';
    }

    public function description(): string
    {
        return 'Transfere o atendimento para uma fila específica quando o assunto não pertence ao objetivo atual.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $queues = $this->available();

        $destination = ['type' => 'string', 'description' => 'Identificador da fila de destino.'];

        if ($queues->isNotEmpty()) {
            $destination['enum'] = $queues->pluck('slug')->all();
            $destination['description'] = 'Fila de destino. Opções: '.$queues
                ->map(fn (ServiceQueue $queue): string => "{$queue->slug} ({$queue->name})")
                ->implode(', ').'.';
        }

        return [
            'type' => 'object',
            'properties' => [
                'queue_slug' => $destination,
                'reason' => ['type' => 'string', 'description' => 'Motivo da transferência.'],
            ],
            'required' => ['queue_slug'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        $queue = ServiceQueue::query()
            ->where('is_active', true)
            ->where('slug', $arguments['queue_slug'] ?? '')
            ->first();

        if ($queue === null) {
            $options = $this->available()->pluck('slug');

            return $options->isEmpty()
                ? 'Nenhum setor está disponível para transferência. Siga o atendimento ou encaminhe para um atendente.'
                : 'Setor não encontrado. Use um destes identificadores: '.$options->implode(', ').'.';
        }

        if ($queue->ai_objective_id !== null && $queue->ai_objective_id === $conversation->ai_objective_id) {
            return "O setor {$queue->name} é atendido por você mesmo. Para falar com uma pessoa use request_human.";
        }

        $conversation->forceFill([
            'status' => ConversationStatus::Pending,
            'ai_objective_id' => null,
        ])->save();

        $this->router->moveToQueue($conversation, $queue);

        return "Atendimento transferido para a fila {$queue->name}.";
    }

    /**
     * @return Collection<int, ServiceQueue>
     */
    private function available(): Collection
    {
        return ServiceQueue::query()->where('is_active', true)->orderBy('name')->get();
    }
}
