<?php

use App\Actions\Ai\HandleAiTurn;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tag;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function aiSays(string $content, array $toolCalls = []): array
{
    return ['choices' => [['message' => array_filter([
        'content' => $content,
        'tool_calls' => $toolCalls,
    ])]]];
}

function aiCallsApplyTag(string $slug): array
{
    return [[
        'id' => 'call-1',
        'type' => 'function',
        'function' => ['name' => 'apply_tag', 'arguments' => '{"tag_slug":"'.$slug.'"}'],
    ]];
}

function aiTurnOn(array $responses): Conversation
{
    Http::fake([
        '*' => Http::sequence(array_map(fn (array $body) => Http::response($body), $responses)),
    ]);

    $objective = AiObjective::factory()->create(['tools' => ['apply_tag', 'transfer_to_queue']]);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    return $conversation;
}

function aiMessagesOf(Conversation $conversation): array
{
    return Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('source', MessageSource::Ai)
        ->orderBy('id')
        ->pluck('body')
        ->all();
}

beforeEach(function (): void {
    Queue::fake();
    tenant();
});

it('asks again when the ai promised an action without calling the tool', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente']);

    $conversation = aiTurnOn([
        aiSays('Vou te encaminhar para um consultor. Um momento, por favor.'),
        aiSays('ok', aiCallsApplyTag('lead-quente')),
        aiSays('Pronto, já está com o time.'),
    ]);

    expect($conversation->tags()->pluck('slug')->all())->toBe(['lead-quente']);
});

it('does not repeat itself to the contact while being reminded', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente']);

    $conversation = aiTurnOn([
        aiSays('Vou te encaminhar para um consultor. Um momento, por favor.'),
        aiSays('ok', aiCallsApplyTag('lead-quente')),
        aiSays('Pronto, já está com o time.'),
    ]);

    expect(aiMessagesOf($conversation))->toBe([
        'Vou te encaminhar para um consultor. Um momento, por favor.',
        'Pronto, já está com o time.',
    ]);
});

it('tells the ai that a promise alone does nothing', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente']);

    aiTurnOn([
        aiSays('Vou transferir agora mesmo.'),
        aiSays('ok', aiCallsApplyTag('lead-quente')),
        aiSays('Feito.'),
    ]);

    Http::assertSent(function ($request): bool {
        $last = collect($request['messages'])->last();

        return $last['role'] === 'user' && str_contains($last['content'], 'nenhuma ferramenta foi chamada');
    });
});

it('leaves an ordinary answer alone instead of forcing a tool', function (): void {
    $conversation = aiTurnOn([
        aiSays('A AC180P tem 1152 Wh de capacidade e 1800 W de potência.'),
    ]);

    expect(aiMessagesOf($conversation))->toBe(['A AC180P tem 1152 Wh de capacidade e 1800 W de potência.']);

    Http::assertSentCount(1);
});

it('reminds the ai only once so a stubborn turn cannot loop', function (): void {
    aiTurnOn([
        aiSays('Vou te transferir agora.'),
        aiSays('Só um instante, vou encaminhar.'),
    ]);

    Http::assertSentCount(2);
});

it('keeps the contact from seeing the ai stall twice in a row', function (): void {
    $conversation = aiTurnOn([
        aiSays('Vou te transferir agora.'),
        aiSays('Só um instante, vou encaminhar.'),
    ]);

    expect(aiMessagesOf($conversation))->toBe(['Vou te transferir agora.']);
});

it('never contradicts itself by telling the model to act only after speaking', function (): void {
    Http::fake(['*' => Http::response(aiSays('oi'))]);

    $objective = AiObjective::factory()->create(['tools' => ['transfer_to_queue']]);

    app(HandleAiTurn::class)->handle(Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]));

    Http::assertSent(function ($request): bool {
        $system = collect($request['messages'])->firstWhere('role', 'system')['content'];

        return str_contains($system, 'na mesma resposta')
            && str_contains($system, 'Uma resposta só de texto não executa nada');
    });
});
