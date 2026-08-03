<?php

namespace App\Domain\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\DataObjects\AiMessage;
use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiResponse;
use App\Domain\Ai\DataObjects\AiToolCall;
use App\Domain\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProvider
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
            ->withHeaders([
                'x-api-key' => (string) ($this->config['api_key'] ?? ''),
                'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
            ])
            ->acceptJson()
            ->timeout(120)
            ->post('/messages', array_filter([
                'model' => $request->model,
                'system' => $request->systemPrompt,
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
                'messages' => $this->messages($request),
                'tools' => $this->tools($request),
            ], fn (mixed $value): bool => $value !== null && $value !== []));

        if ($response->failed()) {
            throw AiException::requestFailed($this->key, $response->json('error.message') ?? $response->body());
        }

        $blocks = collect($response->json('content', []));

        return new AiResponse(
            content: $blocks->where('type', 'text')->pluck('text')->implode("\n") ?: null,
            toolCalls: $blocks->where('type', 'tool_use')
                ->map(fn (array $block): AiToolCall => new AiToolCall(
                    id: (string) data_get($block, 'id'),
                    name: (string) data_get($block, 'name'),
                    arguments: data_get($block, 'input', []),
                ))
                ->values()
                ->all(),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            finishReason: $response->json('stop_reason'),
            raw: $response->json() ?? [],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function messages(AiRequest $request): array
    {
        return array_map(fn ($message): array => match ($message->role) {
            'tool' => [
                'role' => 'user',
                'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => data_get($message->extra, 'tool_call_id'),
                    'content' => $message->content,
                ]],
            ],
            'assistant' => $this->assistantMessage($message),
            default => ['role' => $message->role, 'content' => $message->content],
        }, $request->messages);
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantMessage(AiMessage $message): array
    {
        $toolCalls = data_get($message->extra, 'tool_calls', []);

        if ($toolCalls === []) {
            return ['role' => 'assistant', 'content' => $message->content];
        }

        $blocks = filled($message->content)
            ? [['type' => 'text', 'text' => $message->content]]
            : [];

        foreach ($toolCalls as $call) {
            $blocks[] = [
                'type' => 'tool_use',
                'id' => $call->id,
                'name' => $call->name,
                'input' => (object) $call->arguments,
            ];
        }

        return ['role' => 'assistant', 'content' => $blocks];
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
            'name' => $schema['name'],
            'description' => $schema['description'],
            'input_schema' => $schema['parameters'],
        ], $request->toolSchemas());
    }
}
