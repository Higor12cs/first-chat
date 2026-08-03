<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\DataObjects\AiMessage;
use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiResponse;
use App\Domain\Ai\DataObjects\AiToolCall;
use App\Domain\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleProvider implements AiProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $key,
        private readonly array $config,
    ) {}

    public function chat(AiRequest $request): AiResponse
    {
        $response = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withToken((string) ($this->config['api_key'] ?? ''))
            ->acceptJson()
            ->timeout(120)
            ->post('/chat/completions', array_filter([
                'model' => $request->model,
                'temperature' => $this->temperature($request),
                $this->tokenParam() => $request->maxTokens,
                'messages' => $this->messages($request),
                'tools' => $this->tools($request),
            ], fn (mixed $value): bool => $value !== null && $value !== []));

        if ($response->failed()) {
            throw AiException::requestFailed($this->key, $response->json('error.message') ?? $response->body());
        }

        $choice = $response->json('choices.0', []);

        return new AiResponse(
            content: data_get($choice, 'message.content'),
            toolCalls: $this->toolCalls(data_get($choice, 'message.tool_calls', [])),
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            finishReason: data_get($choice, 'finish_reason'),
            raw: $response->json() ?? [],
        );
    }

    private function tokenParam(): string
    {
        return (string) ($this->config['token_param'] ?? 'max_tokens');
    }

    private function temperature(AiRequest $request): ?float
    {
        $fixed = (array) ($this->config['fixed_temperature_models'] ?? []);

        return in_array($request->model, $fixed, true) ? null : $request->temperature;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function messages(AiRequest $request): array
    {
        $messages = [['role' => 'system', 'content' => $request->systemPrompt]];

        foreach ($request->messages as $message) {
            $messages[] = match ($message->role) {
                'tool' => [
                    'role' => 'tool',
                    'tool_call_id' => data_get($message->extra, 'tool_call_id'),
                    'content' => $message->content,
                ],
                'assistant' => $this->assistantMessage($message),
                default => ['role' => $message->role, 'content' => $message->content],
            };
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantMessage(AiMessage $message): array
    {
        $toolCalls = $this->requestedToolCalls($message);

        return array_filter([
            'role' => 'assistant',
            'content' => $message->content,
            'tool_calls' => $toolCalls,
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requestedToolCalls(AiMessage $message): array
    {
        return array_map(fn (AiToolCall $call): array => [
            'id' => $call->id,
            'type' => 'function',
            'function' => [
                'name' => $call->name,
                'arguments' => (string) json_encode((object) $call->arguments),
            ],
        ], data_get($message->extra, 'tool_calls', []));
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function tools(AiRequest $request): ?array
    {
        if ($request->tools === []) {
            return null;
        }

        return array_map(fn (array $schema): array => [
            'type' => 'function',
            'function' => $schema,
        ], $request->toolSchemas());
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @return array<int, AiToolCall>
     */
    private function toolCalls(array $toolCalls): array
    {
        return array_map(fn (array $call): AiToolCall => new AiToolCall(
            id: (string) data_get($call, 'id'),
            name: (string) data_get($call, 'function.name'),
            arguments: json_decode((string) data_get($call, 'function.arguments', '{}'), true) ?? [],
        ), $toolCalls);
    }
}
