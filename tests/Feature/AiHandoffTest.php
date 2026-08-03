<?php

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Events\Ai\AiHandoffRequested;
use App\Jobs\Ai\RunAiTurn;
use App\Listeners\Ai\HandleAiHandoff;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function handoffConversation(): Conversation
{
    $objective = AiObjective::factory()->create(['is_active' => true]);

    return Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]);
}

function handoffMessages(Conversation $conversation): int
{
    return Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('source', MessageSource::System)
        ->where('body', 'Vou te encaminhar para um de nossos atendentes. Um momento, por favor.')
        ->count();
}

it('hands the conversation to a human when the ai fails for good', function (): void {
    Event::fake([AiHandoffRequested::class]);
    tenant();

    $conversation = handoffConversation();

    (new RunAiTurn($conversation))->failed(new RuntimeException('Unsupported parameter: max_tokens'));

    Event::assertDispatched(
        AiHandoffRequested::class,
        fn (AiHandoffRequested $event): bool => $event->conversation->is($conversation)
            && ! $event->announcedByAi
            && str_contains((string) $event->reason, 'max_tokens'),
    );
});

it('stays quiet when the failed conversation already left the ai', function (): void {
    Event::fake([AiHandoffRequested::class]);
    tenant();

    $conversation = handoffConversation();
    $conversation->forceFill(['ai_objective_id' => null])->save();

    (new RunAiTurn($conversation))->failed(new RuntimeException('qualquer falha'));

    Event::assertNotDispatched(AiHandoffRequested::class);
});

it('warns the contact when the handoff came from the objective limit', function (): void {
    Queue::fake();
    tenant();

    $conversation = handoffConversation();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'source' => MessageSource::Ai,
        'body' => 'Claro, posso ajudar com isso!',
    ]);

    app(HandleAiHandoff::class)->handle(
        new AiHandoffRequested($conversation, $conversation->aiObjective, 'Limite do objetivo atingido.'),
    );

    expect(handoffMessages($conversation))->toBe(1);
});

it('stays quiet when the ai already told the contact it was handing off', function (): void {
    Queue::fake();
    tenant();

    $conversation = handoffConversation();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'source' => MessageSource::Ai,
        'body' => 'Vou te passar para um atendente agora mesmo.',
    ]);

    app(HandleAiHandoff::class)->handle(
        new AiHandoffRequested($conversation, $conversation->aiObjective, 'Cliente pediu.', announcedByAi: true),
    );

    expect(handoffMessages($conversation))->toBe(0);
});

it('warns the contact when the ai asked for a human without saying anything', function (): void {
    Queue::fake();
    tenant();

    $conversation = handoffConversation();

    app(HandleAiHandoff::class)->handle(
        new AiHandoffRequested($conversation, $conversation->aiObjective, 'Cliente pediu.', announcedByAi: true),
    );

    expect(handoffMessages($conversation))->toBe(1);
});

it('releases the conversation from the ai on every handoff', function (): void {
    Queue::fake();
    tenant();

    $conversation = handoffConversation();

    app(HandleAiHandoff::class)->handle(
        new AiHandoffRequested($conversation, $conversation->aiObjective),
    );

    expect($conversation->fresh()->ai_objective_id)->toBeNull()
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Pending);
});
