<?php

use App\Actions\Conversations\StartConversation;
use App\Actions\Messaging\ReceiveInboundMessage;
use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\DataObjects\ContactIdentity;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Conversations\MessageReceived;
use App\Events\Conversations\MessageSent;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Support\Messaging\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function inbound(string $externalId = 'msg-1', string $identifier = '5511999999999'): InboundMessage
{
    return new InboundMessage(
        externalId: $externalId,
        contact: new ContactIdentity(identifier: $identifier, name: 'Maria', phone: $identifier),
        body: 'Olá, preciso de ajuda.',
    );
}

it('creates contact, channel and conversation on the first message', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $message = app(ReceiveInboundMessage::class)->handle($connection, inbound());

    expect($message)->not->toBeNull()
        ->and(Contact::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and($message->conversation->channel)->toBe(Channel::WhatsApp)
        ->and($message->conversation->unread_count)->toBe(1);

    Event::assertDispatched(MessageReceived::class);
});

it('reuses the open conversation for the following messages', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $action = app(ReceiveInboundMessage::class);
    $action->handle($connection, inbound('msg-1'));
    $action->handle($connection, inbound('msg-2'));

    expect(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->first()->unread_count)->toBe(2);
});

it('ignores a message that was already stored', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $action = app(ReceiveInboundMessage::class);
    $first = $action->handle($connection, inbound('duplicated'));
    $second = $action->handle($connection, inbound('duplicated'));

    expect($second->id)->toBe($first->id)
        ->and(Conversation::query()->first()->unread_count)->toBe(1);
});

it('recognises the same number with and without the ninth digit', function (): void {
    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $started = app(StartConversation::class)->handle($connection, '5534992499140', 'Higor');

    app(ReceiveInboundMessage::class)->handle($connection, new InboundMessage(
        externalId: 'msg-1',
        contact: new ContactIdentity(
            identifier: '553492499140@s.whatsapp.net',
            name: 'Higor Carneiro',
            phone: '553492499140',
        ),
        body: 'Bom dia',
    ));

    expect(Contact::query()->count())->toBe(1)
        ->and(ContactChannel::query()->count())->toBe(1)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(Conversation::query()->first()->id)->toBe($started->id);
});

it('does not invent a ninth digit for a landline', function (): void {
    expect(PhoneNumber::variants('553432231122'))->toBe(['553432231122'])
        ->and(PhoneNumber::variants('5534992499140'))->toBe(['5534992499140', '553492499140'])
        ->and(PhoneNumber::variants('553492499140'))->toBe(['553492499140', '5534992499140']);
});

it('keeps the message the contact quoted', function (): void {
    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $action = app(ReceiveInboundMessage::class);
    $quoted = $action->handle($connection, inbound('msg-1'));

    $reply = new InboundMessage(
        externalId: 'msg-2',
        contact: new ContactIdentity(identifier: '5511999999999', name: 'Maria', phone: '5511999999999'),
        body: 'Sim, esta.',
        replyToExternalId: 'msg-1',
    );

    expect($action->handle($connection, $reply)->reply_to_message_id)->toBe($quoted->id);
});

it('lets the database refuse the copy that the check let through', function (): void {
    tenant();
    $conversation = Conversation::factory()->create();

    Message::factory()->create(['conversation_id' => $conversation->id, 'external_id' => 'REPETIDA']);

    expect(fn () => Message::factory()->create([
        'conversation_id' => $conversation->id,
        'external_id' => 'REPETIDA',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('opens a new conversation when the previous one is closed', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $action = app(ReceiveInboundMessage::class);
    $first = $action->handle($connection, inbound('msg-1'));

    $first->conversation->forceFill(['status' => ConversationStatus::Closed, 'closed_at' => now()])->save();

    $action->handle($connection, inbound('msg-2'));

    expect(Conversation::query()->count())->toBe(2)
        ->and(Contact::query()->count())->toBe(1);
});

it('drops the conversation in the waiting sector when no chatbot answers the connection', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    $queue = ServiceQueue::factory()->create(['is_default' => true, 'assignment_strategy' => 'manual']);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => null]);

    $message = app(ReceiveInboundMessage::class)->handle($connection, inbound());

    $conversation = $message->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Pending)
        ->and($conversation->section())->toBe(ConversationSection::Waiting)
        ->and($conversation->service_queue_id)->toBe($queue->id)
        ->and($conversation->assigned_user_id)->toBeNull();
});

it('ignores an inactive chatbot and waits for a human instead', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    $queue = ServiceQueue::factory()->create(['is_default' => true, 'assignment_strategy' => 'manual']);
    $flow = ChatFlow::factory()->create([
        'is_active' => false,
        'nodes' => [['id' => 'start', 'type' => 'start', 'data' => []]],
    ]);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => $flow->id]);

    $message = app(ReceiveInboundMessage::class)->handle($connection, inbound());

    $conversation = $message->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Pending)
        ->and($conversation->chat_flow_id)->toBeNull()
        ->and($conversation->service_queue_id)->toBe($queue->id);
});

it('starts the chatbot when the connection has an active one', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [['id' => 'start', 'type' => 'start', 'data' => []]],
    ]);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => $flow->id]);

    $message = app(ReceiveInboundMessage::class)->handle($connection, inbound());

    $conversation = $message->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Bot)
        ->and($conversation->section())->toBe(ConversationSection::Automatic)
        ->and($conversation->service_queue_id)->toBeNull();
});

it('keeps a group out of every sector', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [['id' => 'start', 'type' => 'start', 'data' => []]],
    ]);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => $flow->id]);

    $inbound = new InboundMessage(
        externalId: 'group-1',
        contact: new ContactIdentity(identifier: '55119999@g.us', name: 'Equipe', isGroup: true),
        body: 'Bom dia, time.',
    );

    $conversation = app(ReceiveInboundMessage::class)->handle($connection, $inbound)->conversation->fresh();

    expect($conversation->is_group)->toBeTrue()
        ->and($conversation->service_queue_id)->toBeNull()
        ->and($conversation->chat_flow_id)->toBeNull()
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->section())->toBe(ConversationSection::Groups);
});
it('parks the conversation in the after hours section when the shift is over', function (): void {
    Event::fake([MessageReceived::class]);

    $tenant = tenant(['timezone' => 'America/Sao_Paulo', 'settings' => [
        'after_hours_enabled' => true,
        'business_hours' => ['1' => ['start' => '08:00', 'end' => '18:00']],
    ]]);

    ServiceQueue::factory()->create(['is_default' => true]);
    $flow = ChatFlow::factory()->create([
        'is_active' => true,
        'nodes' => [['id' => 'start', 'type' => 'start', 'data' => []]],
    ]);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => $flow->id]);

    Carbon::setTestNow(Carbon::parse('2026-07-27 23:00:00', $tenant->timezone));

    $conversation = app(ReceiveInboundMessage::class)->handle($connection, inbound())->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::AfterHours)
        ->and($conversation->section())->toBe(ConversationSection::AfterHours)
        ->and($conversation->chat_flow_id)->toBeNull()
        ->and($conversation->service_queue_id)->toBeNull();

    Carbon::setTestNow();
});

it('routes the conversation normally once the shift starts again', function (): void {
    Event::fake([MessageReceived::class]);

    $tenant = tenant(['timezone' => 'America/Sao_Paulo', 'settings' => [
        'after_hours_enabled' => true,
        'business_hours' => ['1' => ['start' => '08:00', 'end' => '18:00']],
    ]]);

    $queue = ServiceQueue::factory()->create(['is_default' => true, 'assignment_strategy' => 'manual']);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => null]);
    $action = app(ReceiveInboundMessage::class);

    Carbon::setTestNow(Carbon::parse('2026-07-27 23:00:00', $tenant->timezone));
    $action->handle($connection, inbound('msg-noite'));

    Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00', $tenant->timezone));
    $conversation = $action->handle($connection, inbound('msg-manha'))->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Pending)
        ->and($conversation->section())->toBe(ConversationSection::Waiting)
        ->and($conversation->service_queue_id)->toBe($queue->id)
        ->and(Conversation::query()->count())->toBe(1);

    Carbon::setTestNow();
});

it('keeps the business hours out of the way when the account did not set any', function (): void {
    Event::fake([MessageReceived::class]);

    tenant(['settings' => ['after_hours_enabled' => true]]);
    $queue = ServiceQueue::factory()->create(['is_default' => true, 'assignment_strategy' => 'manual']);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => null]);

    $conversation = app(ReceiveInboundMessage::class)->handle($connection, inbound())->conversation->fresh();

    expect($conversation->status)->toBe(ConversationStatus::Pending)
        ->and($conversation->service_queue_id)->toBe($queue->id);
});

it('stores what the account sent from its own phone as an outgoing message', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    $action = app(ReceiveInboundMessage::class);
    $action->handle($connection, inbound('do-contato'));

    $echo = new InboundMessage(
        externalId: 'do-celular',
        contact: new ContactIdentity(identifier: '5511999999999', name: 'Maria', phone: '5511999999999'),
        body: 'Respondi pelo celular.',
        fromMe: true,
    );

    $message = $action->handle($connection, $echo);

    expect($message->direction)->toBe(MessageDirection::Outbound)
        ->and($message->source)->toBe(MessageSource::System)
        ->and($message->body)->toBe('Respondi pelo celular.')
        ->and($message->conversation->fresh()->unread_count)->toBe(1)
        ->and(Conversation::query()->count())->toBe(1);

    Event::assertDispatched(MessageSent::class);
});

it('resolves the encrypted media into an address the browser can open', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    Http::fake([
        '*/message/download' => Http::response([
            'fileURL' => 'https://midia.uazapi.com/files/abc.mp3',
            'mimetype' => 'audio/mpeg',
        ]),
    ]);

    $audio = new InboundMessage(
        externalId: 'msg-audio',
        contact: new ContactIdentity(identifier: '5511999999999', name: 'Maria', phone: '5511999999999'),
        type: MessageType::Audio,
        mediaMimeType: 'audio/ogg; codecs=opus',
    );

    $message = app(ReceiveInboundMessage::class)->handle($connection, $audio);

    expect($message->media_url)->toBe('https://midia.uazapi.com/files/abc.mp3')
        ->and($message->media_mime_type)->toBe('audio/mpeg');
});

it('keeps a text message away from the media download', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    Http::fake();

    app(ReceiveInboundMessage::class)->handle($connection, inbound());

    Http::assertNothingSent();
});

it('stores the message even when the media cannot be downloaded', function (): void {
    Event::fake([MessageReceived::class]);

    tenant();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    Http::fake(['*/message/download' => Http::response(['error' => 'not found'], 404)]);

    $image = new InboundMessage(
        externalId: 'msg-imagem',
        contact: new ContactIdentity(identifier: '5511999999999', name: 'Maria', phone: '5511999999999'),
        type: MessageType::Image,
    );

    $message = app(ReceiveInboundMessage::class)->handle($connection, $image);

    expect($message)->not->toBeNull()
        ->and($message->media_url)->toBeNull();
});
