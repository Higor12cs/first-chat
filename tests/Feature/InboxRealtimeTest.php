<?php

use App\Domain\Ai\Tools\TransferToQueueTool;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\Nodes\AiNode;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\ConversationUpdated;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Services\Conversations\ConversationRouter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('announces the conversation when the ai transfers it to a queue', function (): void {
    Queue::fake();
    tenant();
    Event::fake([ConversationUpdated::class]);

    $queue = ServiceQueue::factory()->create(['slug' => 'financeiro']);
    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Ai]);

    app(TransferToQueueTool::class)->execute($conversation, ['queue_slug' => 'financeiro']);

    expect($conversation->fresh()->service_queue_id)->toBe($queue->id);

    Event::assertDispatched(
        ConversationUpdated::class,
        fn (ConversationUpdated $event): bool => $event->conversation->is($conversation)
    );
});

it('announces the conversation when the router moves it to a queue', function (): void {
    Queue::fake();
    tenant();
    Event::fake([ConversationUpdated::class]);

    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Pending]);

    app(ConversationRouter::class)->moveToQueue($conversation, ServiceQueue::factory()->create());

    Event::assertDispatched(ConversationUpdated::class);
});

it('announces the conversation when the router hands it to an agent', function (): void {
    Queue::fake();
    $tenant = tenant();
    Event::fake([ConversationUpdated::class]);

    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Pending]);

    app(ConversationRouter::class)->assign($conversation, userFor($tenant));

    Event::assertDispatched(ConversationUpdated::class);
});

it('announces the conversation when the flow hands it to the ai', function (): void {
    Queue::fake();
    tenant();
    Event::fake([ConversationUpdated::class]);

    $objective = AiObjective::factory()->create();
    $conversation = Conversation::factory()->create(['status' => ConversationStatus::Bot]);

    (new AiNode)->execute(new FlowContext(
        conversation: $conversation,
        node: ['id' => 'ia', 'type' => 'ai', 'data' => ['ai_objective_id' => $objective->id]],
        state: [],
    ));

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Ai);

    Event::assertDispatched(ConversationUpdated::class);
});
