<?php

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Services\Chatbot\FlowEngine;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = tenant();
});

function flowWaitingForAnswer(int $minutes = 15, string $onNoAction = 'close', ?string $queueId = null): ChatFlow
{
    return ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => [
                'no_action_minutes' => $minutes,
                'no_action' => $onNoAction,
                'no_action_service_queue_id' => $queueId,
            ]],
            ['id' => 'menu', 'type' => 'menu', 'data' => [
                'text' => 'Como podemos ajudar?',
                'options' => [['id' => 'suporte', 'label' => 'Suporte']],
            ]],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'menu', 'sourceHandle' => null]],
    ]);
}

it('keeps a conversation the chatbot is handling in the automatic section', function (): void {
    $flow = flowWaitingForAnswer();
    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Pending]);

    app(FlowEngine::class)->restart($conversation, $flow);

    $conversation = $conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Bot)
        ->and($conversation->section())->toBe(ConversationSection::Automatic)
        ->and($conversation->no_action_at)->not->toBeNull();
});

it('arms the no action deadline the start block configured', function (): void {
    $flow = flowWaitingForAnswer(minutes: 30);
    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    expect(now()->diffInMinutes($conversation->fresh()->no_action_at))->toBeGreaterThanOrEqual(29);
});

it('finishes the conversation when the contact never answers', function (): void {
    $flow = flowWaitingForAnswer();
    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $this->travel(20)->minutes();

    $this->artisan('chatbot:timeouts')->assertSuccessful();

    $conversation = $conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Closed)
        ->and($conversation->no_action_at)->toBeNull()
        ->and(Message::query()->where('source', 'bot')->latest('id')->first()->body)
        ->toBe((string) config('chatbot.no_action_message'));
});

it('leaves the conversation alone before the deadline', function (): void {
    $flow = flowWaitingForAnswer(minutes: 30);
    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $this->travel(5)->minutes();

    $this->artisan('chatbot:timeouts')->assertSuccessful();

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Bot);
});

it('can hand an abandoned conversation to a sector instead of closing it', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'manual']);
    $flow = flowWaitingForAnswer(onNoAction: 'queue', queueId: $queue->id);
    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $this->travel(20)->minutes();

    $this->artisan('chatbot:timeouts')->assertSuccessful();

    $conversation = $conversation->fresh();

    expect($conversation->section())->toBe(ConversationSection::Waiting)
        ->and($conversation->service_queue_id)->toBe($queue->id)
        ->and($conversation->no_action_at)->toBeNull();
});

it('sends a flow transfer to the waiting section of the chosen sector', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'manual']);

    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'setor', 'type' => 'queue', 'data' => ['service_queue_id' => $queue->id]],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'setor', 'sourceHandle' => null]],
    ]);

    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $conversation = $conversation->fresh();

    expect($conversation->section())->toBe(ConversationSection::Waiting)
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->no_action_at)->toBeNull();
});

it('sends a flow transfer straight to manual when it names an agent of the sector', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'manual']);
    $agent = userFor($this->tenant);
    $agent->serviceQueues()->sync([$queue->id]);

    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'setor', 'type' => 'queue', 'data' => [
                'service_queue_id' => $queue->id,
                'user_id' => $agent->id,
            ]],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'setor', 'sourceHandle' => null]],
    ]);

    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $conversation = $conversation->fresh();

    expect($conversation->section())->toBe(ConversationSection::Manual)
        ->and($conversation->assigned_user_id)->toBe($agent->id)
        ->and($conversation->service_queue_id)->toBe($queue->id);
});

it('ignores an agent who does not answer for the chosen sector', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'manual']);
    $outsider = userFor($this->tenant);

    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'setor', 'type' => 'queue', 'data' => [
                'service_queue_id' => $queue->id,
                'user_id' => $outsider->id,
            ]],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'setor', 'sourceHandle' => null]],
    ]);

    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    expect($conversation->fresh()->section())->toBe(ConversationSection::Waiting);
});

it('closes the conversation from the finish block', function (): void {
    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'fim', 'type' => 'close', 'data' => ['text' => 'Até logo!', 'reason' => 'Encerrado pelo chatbot.']],
        ],
        'edges' => [['id' => 'e1', 'source' => 'start', 'target' => 'fim', 'sourceHandle' => null]],
    ]);

    $conversation = Conversation::factory()->create();

    app(FlowEngine::class)->restart($conversation, $flow);

    $conversation = $conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Closed)
        ->and($conversation->no_action_at)->toBeNull()
        ->and(Message::query()->latest('id')->first()->body)->toBe('Até logo!');
});
