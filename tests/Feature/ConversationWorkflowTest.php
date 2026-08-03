<?php

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Jobs\Chatbot\AdvanceChatFlow;
use App\Jobs\Messaging\DeliverMessage;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
    $this->conversation = Conversation::factory()->create(['unread_count' => 3]);
});

it('sends a reply and queues the delivery', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    $message = Message::query()->latest('id')->first();

    expect($message->body)->toBe('Bom dia!')
        ->and($message->direction)->toBe(MessageDirection::Outbound)
        ->and($message->source)->toBe(MessageSource::Agent)
        ->and($message->user_id)->toBe($this->user->id);

    Queue::assertPushed(DeliverMessage::class);
});

it('refuses to reply without the permission', function (): void {
    $viewer = userFor($this->tenant, ['conversations.view']);

    $this->actingAs($viewer)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertForbidden();

    expect(Message::query()->count())->toBe(0);
});

it('clears the unread counter', function (): void {
    $this->actingAs($this->user)
        ->post("/atendimentos/{$this->conversation->id}/lida")
        ->assertRedirect();

    expect($this->conversation->fresh()->unread_count)->toBe(0);
});

it('sends the conversation straight to an agent of the chosen sector', function (): void {
    $queue = ServiceQueue::factory()->create();
    $agent = userFor($this->tenant);

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'manual',
            'service_queue_id' => $queue->id,
            'user_id' => $agent->id,
        ])
        ->assertRedirect();

    $conversation = $this->conversation->fresh();

    expect($conversation->assigned_user_id)->toBe($agent->id)
        ->and($conversation->service_queue_id)->toBe($queue->id)
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->section())->toBe(ConversationSection::Manual);
});

it('leaves the conversation waiting for the sector with nobody owning it', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'round_robin']);
    $agent = userFor($this->tenant);
    $queue->users()->sync([$agent->id]);

    $this->conversation->forceFill(['assigned_user_id' => $this->user->id])->save();

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'waiting',
            'service_queue_id' => $queue->id,
        ])
        ->assertRedirect();

    $conversation = $this->conversation->fresh();

    expect($conversation->service_queue_id)->toBe($queue->id)
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->status)->toBe(ConversationStatus::Pending)
        ->and($conversation->section())->toBe(ConversationSection::Waiting);
});

it('refuses a transfer to the manual without a sector and a user', function (): void {
    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", ['section' => 'manual'])
        ->assertSessionHasErrors(['service_queue_id', 'user_id']);
});

it('refuses a transfer that does not name a destination', function (): void {
    $queue = ServiceQueue::factory()->create();

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", ['service_queue_id' => $queue->id])
        ->assertSessionHasErrors('section');
});

it('sends the conversation back to the chatbot at the chosen level', function (): void {
    $flow = ChatFlow::factory()->create([
        'nodes' => [
            ['id' => 'start', 'type' => 'start', 'data' => []],
            ['id' => 'menu', 'type' => 'menu', 'data' => ['label' => 'Menu Inicial']],
        ],
        'edges' => [],
    ]);

    $this->conversation->forceFill(['assigned_user_id' => $this->user->id])->save();

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'automatic',
            'chat_flow_id' => $flow->id,
            'node_id' => 'menu',
        ])
        ->assertRedirect();

    $conversation = $this->conversation->fresh();

    expect($conversation->chat_flow_id)->toBe($flow->id)
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->status)->toBe(ConversationStatus::Bot)
        ->and($conversation->section())->toBe(ConversationSection::Automatic)
        ->and(data_get($conversation->flow_state, 'node_id'))->toBe('menu');

    Queue::assertPushed(AdvanceChatFlow::class);
});

it('refuses a level that does not belong to the chatbot', function (): void {
    $flow = ChatFlow::factory()->create([
        'nodes' => [['id' => 'start', 'type' => 'start', 'data' => []]],
        'edges' => [],
    ]);

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'automatic',
            'chat_flow_id' => $flow->id,
            'node_id' => 'inexistente',
        ])
        ->assertSessionHasErrors('node_id');
});

it('refuses to transfer a group, since groups belong to no sector', function (): void {
    $group = Conversation::factory()->create(['is_group' => true]);
    $queue = ServiceQueue::factory()->create();

    $this->actingAs($this->user)
        ->put("/atendimentos/{$group->id}/transferencia", [
            'section' => 'waiting',
            'service_queue_id' => $queue->id,
        ])
        ->assertForbidden();
});

it('gives the conversation a sector when it is taken from the waiting', function (): void {
    ServiceQueue::factory()->create(['is_default' => false]);
    $default = ServiceQueue::factory()->create(['is_default' => true]);

    $waiting = Conversation::factory()->create([
        'status' => ConversationStatus::Pending,
        'assigned_user_id' => null,
        'service_queue_id' => null,
    ]);

    $this->actingAs($this->user)
        ->post("/atendimentos/{$waiting->id}/assumir")
        ->assertRedirect();

    $waiting = $waiting->fresh();

    expect($waiting->assigned_user_id)->toBe($this->user->id)
        ->and($waiting->service_queue_id)->toBe($default->id)
        ->and($waiting->section())->toBe(ConversationSection::Manual);
});

it('keeps the sector the conversation already had when it is taken', function (): void {
    ServiceQueue::factory()->create(['is_default' => true]);
    $queue = ServiceQueue::factory()->create();

    $waiting = Conversation::factory()->create([
        'status' => ConversationStatus::Pending,
        'assigned_user_id' => null,
        'service_queue_id' => $queue->id,
    ]);

    $this->actingAs($this->user)
        ->post("/atendimentos/{$waiting->id}/assumir")
        ->assertRedirect();

    expect($waiting->fresh()->service_queue_id)->toBe($queue->id);
});

it('stops the chatbot when someone takes the conversation over', function (): void {
    $bot = Conversation::factory()->create([
        'status' => ConversationStatus::Bot,
        'assigned_user_id' => null,
        'flow_state' => ['node_id' => 'menu', 'awaiting' => 'menu', 'finished' => false],
    ]);

    ServiceQueue::factory()->create(['is_default' => true]);

    $this->actingAs($this->user)
        ->post("/atendimentos/{$bot->id}/assumir")
        ->assertRedirect();

    $bot = $bot->fresh();

    expect($bot->status)->toBe(ConversationStatus::Open)
        ->and($bot->assigned_user_id)->toBe($this->user->id)
        ->and(data_get($bot->flow_state, 'finished'))->toBeTrue()
        ->and($bot->no_action_at)->toBeNull();
});

it('applies tags and runs their automation', function (): void {
    $queue = ServiceQueue::factory()->create();
    $tag = Tag::factory()->create(['automation' => ['service_queue_id' => $queue->id]]);

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/tags", ['tags' => [$tag->id]])
        ->assertRedirect();

    expect($this->conversation->fresh()->tags)->toHaveCount(1)
        ->and($this->conversation->fresh()->service_queue_id)->toBe($queue->id);
});

it('records an internal note', function (): void {
    $this->actingAs($this->user)
        ->post("/atendimentos/{$this->conversation->id}/notas", ['body' => 'Cliente pediu retorno amanhã.'])
        ->assertRedirect();

    $note = ConversationNote::query()->first();

    expect($note->body)->toBe('Cliente pediu retorno amanhã.')
        ->and($note->user_id)->toBe($this->user->id);
});

it('closes and reopens the conversation', function (): void {
    $this->actingAs($this->user)->post("/atendimentos/{$this->conversation->id}/encerrar")->assertRedirect();

    expect($this->conversation->fresh()->status)->toBe(ConversationStatus::Closed)
        ->and($this->conversation->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($this->user)->post("/atendimentos/{$this->conversation->id}/reabrir")->assertRedirect();

    expect($this->conversation->fresh()->status)->not->toBe(ConversationStatus::Closed)
        ->and($this->conversation->fresh()->closed_at)->toBeNull();
});

it('hides other agents conversations from a restricted user', function (): void {
    $restricted = userFor($this->tenant, null, ['hides_other_conversations' => true]);
    $other = userFor($this->tenant);

    Conversation::factory()->create(['assigned_user_id' => $other->id]);
    $own = Conversation::factory()->create(['assigned_user_id' => $restricted->id]);

    $this->actingAs($restricted)
        ->get('/atendimentos')
        ->assertInertia(fn ($page) => expect(conversationIdsIn($page->toArray()['props']['sections']))->toBe([$own->id]));
});

it('shows unassigned conversations of the queues the agent belongs to', function (): void {
    $queue = ServiceQueue::factory()->create();
    $agent = userFor($this->tenant, null, ['hides_other_conversations' => true]);
    $agent->serviceQueues()->sync([$queue->id]);

    $waiting = Conversation::factory()->create(['service_queue_id' => $queue->id, 'assigned_user_id' => null]);
    Conversation::factory()->create(['assigned_user_id' => userFor($this->tenant)->id]);

    $this->actingAs($agent)
        ->get('/atendimentos')
        ->assertInertia(fn ($page) => expect(conversationIdsIn($page->toArray()['props']['sections']))->toBe([$waiting->id]));
});

it('signs the reply with the agent name when the account asks for it', function (): void {
    $this->tenant->update(['settings' => ['sign_messages' => true]]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)->toBe("#*_{$this->user->name}:_*\nBom dia!");
});

it('lets the agent preference override the account default', function (): void {
    $this->tenant->update(['settings' => ['sign_messages' => true]]);

    $agent = userFor($this->tenant, null, ['signs_messages' => false]);

    $this->actingAs($agent)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)->toBe('Bom dia!');
});

it('leaves the reply untouched when nobody signs', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)->toBe('Bom dia!');
});

it('cancels a message that is still waiting for the provider', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'direction' => MessageDirection::Outbound,
        'status' => MessageStatus::Failed,
    ]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}/cancelar")
        ->assertOk();

    expect($message->fresh()->status)->toBe(MessageStatus::Canceled)
        ->and($message->fresh()->error)->toBeNull();
});

it('refuses to cancel a message the contact already received', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'direction' => MessageDirection::Outbound,
        'status' => MessageStatus::Delivered,
    ]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}/cancelar")
        ->assertStatus(422);

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered);
});

it('holds the delivery of a canceled message', function (): void {
    Queue::fake([]);

    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'direction' => MessageDirection::Outbound,
        'status' => MessageStatus::Canceled,
    ]);

    Http::fake();

    app(DeliverMessage::class, ['message' => $message])->handle(app(ConnectorManager::class));

    Http::assertNothingSent();
});

it('keeps the deleted message and its text in the history', function (): void {
    $message = Message::factory()->outbound()->create([
        'conversation_id' => $this->conversation->id,
        'body' => 'Mandei sem querer.',
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}")
        ->assertOk();

    $message = $message->fresh();

    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(MessageStatus::Deleted)
        ->and($message->body)->toBe('Mandei sem querer.');
});

it('refuses to delete a message without the permission', function (): void {
    $agent = userFor($this->tenant, ['conversations.view', 'conversations.reply']);
    $message = Message::factory()->create(['conversation_id' => $this->conversation->id]);

    $this->actingAs($agent)
        ->deleteJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}")
        ->assertForbidden();

    expect($message->fresh()->status)->not->toBe(MessageStatus::Deleted);
});

it('marks a transfer once in the thread instead of one line per step', function (): void {
    $queue = ServiceQueue::factory()->create();
    $agent = userFor($this->tenant);

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'manual',
            'service_queue_id' => $queue->id,
            'user_id' => $agent->id,
        ])
        ->assertRedirect();

    $timeline = collect(
        $this->actingAs($this->user)
            ->get("/atendimentos/{$this->conversation->id}")
            ->viewData('page')['props']['timeline']
    )->where('kind', 'transfer');

    expect($timeline)->toHaveCount(1)
        ->and($timeline->first()['label'])->toBe("Transferido para {$agent->name} no Setor {$queue->name}");
});

it('names the waiting sector in the thread when nobody takes the conversation', function (): void {
    $queue = ServiceQueue::factory()->create(['assignment_strategy' => 'manual']);

    $this->actingAs($this->user)
        ->put("/atendimentos/{$this->conversation->id}/transferencia", [
            'section' => 'waiting',
            'service_queue_id' => $queue->id,
        ])
        ->assertRedirect();

    $timeline = collect(
        $this->actingAs($this->user)
            ->get("/atendimentos/{$this->conversation->id}")
            ->viewData('page')['props']['timeline']
    )->where('kind', 'transfer');

    expect($timeline)->toHaveCount(1)
        ->and($timeline->first()['label'])->toBe("Enviado para o Aguardando do Setor {$queue->name}");
});
