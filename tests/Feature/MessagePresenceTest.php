<?php

use App\Actions\Messaging\UpdateMessageStatus;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Messaging\DataObjects\DeliveryStatusUpdate;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Jobs\Messaging\DeliverMessage;
use App\Jobs\Messaging\RevokeMessage;
use App\Jobs\Messaging\SendReadReceipt;
use App\Jobs\Messaging\SendTypingIndicator;
use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);

    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
    $this->conversation = Conversation::factory()->create(['unread_count' => 2]);
});

function readReceiptFor(Conversation $conversation): void
{
    (new SendReadReceipt($conversation))->handle(app(ConnectorManager::class));
}

it('confirms the reading to the contact when an agent opens the conversation', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->post("/atendimentos/{$this->conversation->id}/lida")
        ->assertRedirect();

    Queue::assertPushed(SendReadReceipt::class);
});

it('confirms the reading even when the counter is already zero', function (): void {
    Queue::fake();

    $this->conversation->forceFill(['unread_count' => 0])->save();

    $this->actingAs($this->user)->post("/atendimentos/{$this->conversation->id}/lida");

    Queue::assertPushed(SendReadReceipt::class);
});

it('announces to the contact that somebody is writing', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/digitando", ['typing' => true])
        ->assertOk();

    Queue::assertPushed(
        SendTypingIndicator::class,
        fn (SendTypingIndicator $job): bool => $job->typing === true
            && $job->conversation->id === $this->conversation->id,
    );
});

it('stops announcing when the agent gives up writing', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/digitando", ['typing' => false])
        ->assertOk();

    Queue::assertPushed(SendTypingIndicator::class, fn (SendTypingIndicator $job): bool => $job->typing === false);
});

it('says nothing on a conversation nobody answers anymore', function (): void {
    Queue::fake();

    $this->conversation->forceFill(['status' => ConversationStatus::Closed, 'closed_at' => now()])->save();

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/digitando", ['typing' => true])
        ->assertOk();

    Queue::assertNotPushed(SendTypingIndicator::class);
});

it('refuses the typing indicator from somebody who cannot answer', function (): void {
    Queue::fake();

    $viewer = userFor($this->tenant, ['conversations.view']);

    $this->actingAs($viewer)
        ->postJson("/atendimentos/{$this->conversation->id}/digitando", ['typing' => true])
        ->assertForbidden();

    Queue::assertNotPushed(SendTypingIndicator::class);
});

it('reaches the provider with the typing of the agent', function (): void {
    Http::fake(['provider.test/*' => Http::response([])]);

    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    (new SendTypingIndicator($conversation))->handle(app(ConnectorManager::class));

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/message/presence')
        && $request['number'] === $conversation->contactChannel->identifier
        && $request['presence'] === 'composing');
});

it('marks what the contact sent and never marks the same messages twice', function (): void {
    Http::fake(['provider.test/*' => Http::response(['results' => []])]);

    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    Message::factory()->create(['conversation_id' => $conversation->id, 'external_id' => 'RECEBIDA-1']);
    Message::factory()->create(['conversation_id' => $conversation->id, 'external_id' => 'RECEBIDA-2']);

    Message::factory()->create(['conversation_id' => $conversation->id, 'external_id' => null]);

    readReceiptFor($conversation);

    Http::assertSent(function ($request): bool {
        $ids = $request['id'];
        sort($ids);

        return str_ends_with($request->url(), '/message/markread') && $ids === ['RECEBIDA-1', 'RECEBIDA-2'];
    });

    expect(Message::query()->whereNotNull('read_at')->count())->toBe(2);

    readReceiptFor($conversation->fresh());

    Http::assertSentCount(1);
});

it('stays quiet while the connection is down', function (): void {
    Http::fake();

    $connection = ChannelConnection::factory()->disconnected()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    Message::factory()->create(['conversation_id' => $conversation->id, 'external_id' => 'RECEBIDA-1']);

    readReceiptFor($conversation);

    Http::assertNothingSent();
});

it('takes the deleted message back from the contact', function (): void {
    Queue::fake();

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $this->conversation->id,
        'external_id' => 'ENVIADA-1',
        'status' => MessageStatus::Sent,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}")
        ->assertOk();

    expect($message->fresh()->status)->toBe(MessageStatus::Deleted);

    Queue::assertPushed(RevokeMessage::class, fn (RevokeMessage $job): bool => $job->message->id === $message->id);
});

it('refuses to delete a message the contact sent', function (): void {
    Queue::fake();

    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'external_id' => 'RECEBIDA-1',
        'status' => MessageStatus::Delivered,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}")
        ->assertStatus(422);

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered);

    Queue::assertNotPushed(RevokeMessage::class);
});

it('marks the message the contact deleted', function (): void {
    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'RECEBIDA-1',
        'status' => MessageStatus::Delivered,
        'body' => 'Mandei o número errado.',
    ]);

    app(UpdateMessageStatus::class)->handle($connection, new DeliveryStatusUpdate(
        externalId: 'RECEBIDA-1',
        status: MessageStatus::Deleted,
    ));

    expect($message->fresh()->status)->toBe(MessageStatus::Deleted)
        ->and($message->fresh()->body)->toBe('Mandei o número errado.');
});

it('walks the deletion from the provider callback to the history', function (): void {
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
    ]);
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'external_id' => '3EB063ABC41D74ED5DB426',
        'status' => MessageStatus::Delivered,
        'body' => 'Teste de mensagem apagada',
    ]);

    $this->postJson("/api/webhooks/conectores/{$connection->id}", [
        'EventType' => 'messages_update',
        'token' => 'instance-token',
        'event' => [
            'Chat' => '553492499140@s.whatsapp.net',
            'IsFromMe' => false,
            'MessageIDs' => ['3EB063ABC41D74ED5DB426'],
            'Timestamp' => '2026-07-30T13:35:56Z',
            'Type' => 'Deleted',
        ],
        'state' => 'Deleted',
        'type' => 'DeletedMessage',
    ])->assertOk();

    expect($message->fresh()->status)->toBe(MessageStatus::Deleted)
        ->and($message->fresh()->body)->toBe('Teste de mensagem apagada');
});

it('turns the delivery receipt into the ticks of the message', function (): void {
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
    ]);
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);
    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => '3EB02AB51741262937E0A9',
        'status' => MessageStatus::Sent,
    ]);

    $this->postJson("/api/webhooks/conectores/{$connection->id}", [
        'EventType' => 'messages_update',
        'token' => 'instance-token',
        'event' => [
            'MessageIDs' => ['3EB02AB51741262937E0A9'],
            'Timestamp' => 1785418567,
            'Type' => 'Delivered',
        ],
        'state' => 'Delivered',
    ])->assertOk();

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and($message->fresh()->delivered_at)->not->toBeNull();
});

it('has nothing to take back when the message never left', function (): void {
    Queue::fake();

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $this->conversation->id,
        'external_id' => null,
        'status' => MessageStatus::Pending,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}")
        ->assertOk();

    Queue::assertNotPushed(RevokeMessage::class);
});

it('erases the message from the contact through the provider', function (): void {
    Http::fake(['provider.test/*' => Http::response(['status' => 'Deleted'])]);

    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'ENVIADA-9',
        'status' => MessageStatus::Deleted,
    ]);

    (new RevokeMessage($message))->handle(app(ConnectorManager::class));

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/message/delete')
        && $request['id'] === 'ENVIADA-9');
});

it('takes back what the provider accepted while the agent was deleting it', function (): void {
    Queue::fake();

    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => null,
        'status' => MessageStatus::Pending,
    ]);

    Http::fake(function () use ($message) {
        Message::query()->whereKey($message->id)->update(['status' => MessageStatus::Deleted]);

        return Http::response(['messageid' => 'ATRASADA-1']);
    });

    (new DeliverMessage($message))->handle(app(ConnectorManager::class));

    expect($message->fresh()->status)->toBe(MessageStatus::Deleted)
        ->and($message->fresh()->external_id)->toBe('ATRASADA-1');

    Queue::assertPushed(RevokeMessage::class);
});

it('records the delivery and then the reading of an outgoing message', function (): void {
    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'ENVIADA-1',
        'status' => MessageStatus::Sent,
    ]);

    $action = app(UpdateMessageStatus::class);

    $action->handle($connection, new DeliveryStatusUpdate('ENVIADA-1', MessageStatus::Delivered));

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and($message->fresh()->delivered_at)->not->toBeNull()
        ->and($message->fresh()->read_at)->toBeNull();

    $action->handle($connection, new DeliveryStatusUpdate('ENVIADA-1', MessageStatus::Read));

    expect($message->fresh()->status)->toBe(MessageStatus::Read)
        ->and($message->fresh()->read_at)->not->toBeNull();
});

it('reports a failure the provider only admits after acknowledging the message', function (): void {
    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'ENVIADA-2',
        'status' => MessageStatus::Sent,
    ]);

    app(UpdateMessageStatus::class)->handle(
        $connection,
        new DeliveryStatusUpdate('ENVIADA-2', MessageStatus::Failed, error: 'Número inexistente.'),
    );

    expect($message->fresh()->status)->toBe(MessageStatus::Failed)
        ->and($message->fresh()->error)->toBe('Número inexistente.');
});

it('ignores a failure that arrives after the contact already got the message', function (): void {
    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'ENVIADA-3',
        'status' => MessageStatus::Delivered,
    ]);

    app(UpdateMessageStatus::class)->handle(
        $connection,
        new DeliveryStatusUpdate('ENVIADA-3', MessageStatus::Failed, error: 'Falhou.'),
    );

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and($message->fresh()->error)->toBeNull();
});

it('clears the previous error once the message goes through', function (): void {
    $connection = ChannelConnection::factory()->create();
    $conversation = Conversation::factory()->create(['channel_connection_id' => $connection->id]);

    $message = Message::factory()->outbound()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'ENVIADA-4',
        'status' => MessageStatus::Failed,
        'error' => 'Tempo esgotado.',
    ]);

    app(UpdateMessageStatus::class)->handle($connection, new DeliveryStatusUpdate('ENVIADA-4', MessageStatus::Delivered));

    expect($message->fresh()->status)->toBe(MessageStatus::Delivered)
        ->and($message->fresh()->error)->toBeNull();
});
