<?php

use App\Domain\Ai\DataObjects\AiMessage;
use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiToolCall;
use App\Domain\Ai\Providers\AnthropicProvider;
use App\Domain\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Support\Facades\Http;

function turnWithToolCall(): AiRequest
{
    return new AiRequest(
        model: 'modelo',
        systemPrompt: 'Você atende.',
        messages: [
            AiMessage::user('Quero falar com o comercial.'),
            AiMessage::assistant('', [new AiToolCall('call-1', 'transfer_to_queue', ['queue_slug' => 'comercial'])]),
            AiMessage::tool('call-1', 'transfer_to_queue', 'Transferido para Comercial.'),
        ],
    );
}

it('keeps the assistant content when the model only asked for a tool', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', ['base_url' => 'https://api.openai.com/v1']))
        ->chat(turnWithToolCall());

    Http::assertSent(function ($request): bool {
        $assistant = collect($request['messages'])->firstWhere('role', 'assistant');

        return array_key_exists('content', $assistant) && $assistant['content'] !== null;
    });
});

it('sends the requested tool calls back with the assistant turn', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', ['base_url' => 'https://api.openai.com/v1']))
        ->chat(turnWithToolCall());

    Http::assertSent(function ($request): bool {
        $assistant = collect($request['messages'])->firstWhere('role', 'assistant');

        return $assistant['tool_calls'] === [[
            'id' => 'call-1',
            'type' => 'function',
            'function' => [
                'name' => 'transfer_to_queue',
                'arguments' => '{"queue_slug":"comercial"}',
            ],
        ]];
    });
});

it('leaves the tool calls out of an ordinary assistant turn', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', ['base_url' => 'https://api.openai.com/v1']))->chat(new AiRequest(
        model: 'modelo',
        systemPrompt: 'Você atende.',
        messages: [AiMessage::user('Oi'), AiMessage::assistant('Olá!')],
    ));

    Http::assertSent(function ($request): bool {
        $assistant = collect($request['messages'])->firstWhere('role', 'assistant');

        return $assistant === ['role' => 'assistant', 'content' => 'Olá!'];
    });
});

it('turns the requested tool calls into anthropic blocks', function (): void {
    Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]])]);

    (new AnthropicProvider('anthropic', ['base_url' => 'https://api.anthropic.com/v1']))
        ->chat(turnWithToolCall());

    Http::assertSent(function ($request): bool {
        $block = collect($request['messages'])->firstWhere('role', 'assistant')['content'][0];

        return $block['type'] === 'tool_use'
            && $block['id'] === 'call-1'
            && $block['name'] === 'transfer_to_queue'
            && (array) $block['input'] === ['queue_slug' => 'comercial'];
    });
});

it('sends the token limit under the parameter name the provider expects', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', [
        'base_url' => 'https://api.openai.com/v1',
        'token_param' => 'max_completion_tokens',
    ]))->chat(new AiRequest(
        model: 'modelo',
        systemPrompt: 'Você atende.',
        messages: [AiMessage::user('Oi')],
        maxTokens: 800,
    ));

    Http::assertSent(fn ($request): bool => $request['max_completion_tokens'] === 800
        && ! array_key_exists('max_tokens', $request->data()));
});

it('keeps max_tokens for providers without a configured token parameter', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', ['base_url' => 'https://api.openai.com/v1']))->chat(new AiRequest(
        model: 'modelo',
        systemPrompt: 'Você atende.',
        messages: [AiMessage::user('Oi')],
        maxTokens: 800,
    ));

    Http::assertSent(fn ($request): bool => $request['max_tokens'] === 800);
});

it('omits the temperature for models that only accept the default', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', [
        'base_url' => 'https://api.openai.com/v1',
        'fixed_temperature_models' => ['modelo-de-raciocinio'],
    ]))->chat(new AiRequest(
        model: 'modelo-de-raciocinio',
        systemPrompt: 'Você atende.',
        messages: [AiMessage::user('Oi')],
        temperature: 0.7,
    ));

    Http::assertSent(fn ($request): bool => ! array_key_exists('temperature', $request->data()));
});

it('keeps the temperature for models that accept it', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    (new OpenAiCompatibleProvider('openai', [
        'base_url' => 'https://api.openai.com/v1',
        'fixed_temperature_models' => ['modelo-de-raciocinio'],
    ]))->chat(new AiRequest(
        model: 'gpt-4o-mini',
        systemPrompt: 'Você atende.',
        messages: [AiMessage::user('Oi')],
        temperature: 0.3,
    ));

    Http::assertSent(fn ($request): bool => $request['temperature'] === 0.3);
});

it('configures the openai provider for the current chat completions parameters', function (): void {
    expect(config('ai.providers.openai.token_param'))->toBe('max_completion_tokens')
        ->and(config('ai.providers.openai.fixed_temperature_models'))->toBe([]);
});

it('offers only the mini models to the user', function (): void {
    expect(config('ai.providers'))->toHaveKeys(['openai'])
        ->and(config('ai.providers.openai.models'))->toBe(['gpt-4o-mini', 'gpt-5.4-mini']);
});

it('prices every model it offers so the cost limit keeps working', function (): void {
    $offered = collect(config('ai.providers'))->flatMap(fn (array $provider): array => $provider['models'] ?? []);

    $pricing = (array) config('ai.pricing', []);

    expect($offered)->not->toBeEmpty()
        ->each(fn ($model) => expect($pricing)->toHaveKey($model->value));
});
