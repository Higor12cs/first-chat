<?php

use App\Actions\Messaging\ProvisionConnection;
use App\Domain\Messaging\Contracts\MessagingConnector;
use App\Domain\Messaging\DataObjects\DeliveryStatusUpdate;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Domain\Messaging\DataObjects\OutgoingMessage;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Domain\Messaging\Exceptions\ConnectorException;
use App\Jobs\Messaging\ProcessConnectorEvent;
use App\Models\ChannelConnection;
use App\Models\ServiceQueue;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);

    tenant();
});

function uazapi(): MessagingConnector
{
    return app(ConnectorManager::class)->for(
        ChannelConnection::factory()->create([
            'driver' => 'uazapi',
            'credentials' => ['token' => 'instance-token'],
        ]),
    );
}

it('opens a session with a json object, never an empty array', function (): void {
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'connecting']])]);

    uazapi()->connect();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && $request->body() === '{}'
        && $request->header('token') === ['instance-token']);
});

it('asks for a pairing code by sending the phone number', function (): void {
    Http::fake(['provider.test/*' => Http::response([
        'instance' => ['status' => 'connecting', 'paircode' => 'WZTK-9QLM', 'qrcode' => ''],
    ])]);

    $update = uazapi()->pairWithPhone('5511988887777');

    expect($update->pairCode)->toBe('WZTK-9QLM')
        ->and($update->qrCode)->toBeNull();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && $request['phone'] === '5511988887777');
});

it('reads the qr code and the state the provider reports', function (): void {
    Http::fake(['provider.test/*' => Http::response([
        'connected' => false,
        'loggedIn' => false,
        'instance' => [
            'status' => 'connecting',
            'qrcode' => 'data:image/png;base64,iVBORw0KGgo=',
            'paircode' => '',
            'owner' => '',
            'profileName' => '',
        ],
    ])]);

    $update = uazapi()->connect();

    expect($update->status)->toBe(ConnectionStatus::Connecting)
        ->and($update->qrCode)->toBe('data:image/png;base64,iVBORw0KGgo=')
        ->and($update->externalIdentifier)->toBeNull()
        ->and($update->metadata)->toBe([]);
});

it('reads the owner once the pairing completes', function (): void {
    Http::fake(['provider.test/*' => Http::response([
        'instance' => [
            'status' => 'connected',
            'qrcode' => '',
            'owner' => '5511988887777',
            'profileName' => 'Padaria do João',
        ],
    ])]);

    $update = uazapi()->status();

    expect($update->status)->toBe(ConnectionStatus::Connected)
        ->and($update->qrCode)->toBeNull()
        ->and($update->externalIdentifier)->toBe('5511988887777')
        ->and($update->metadata)->toBe(['profile_name' => 'Padaria do João']);
});

it('treats a hibernated session as offline', function (): void {
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'hibernated']])]);

    expect(uazapi()->status()->status)->toBe(ConnectionStatus::Disconnected);
});

it('marks messages read by id alone', function (): void {
    Http::fake(['provider.test/*' => Http::response(['results' => []])]);

    uazapi()->markAsRead('5511988887777', 'MENSAGEM-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/message/markread')
        && $request['id'] === ['MENSAGEM-1']
        && ! array_key_exists('number', $request->data()));
});

it('shows the typing for a window short enough to expire on its own', function (): void {
    Http::fake(['provider.test/*' => Http::response([])]);

    uazapi()->sendTyping('5511988887777');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/message/presence')
        && $request['presence'] === 'composing'
        && $request['delay'] === 10000);
});

it('drops the typing without asking for a window', function (): void {
    Http::fake(['provider.test/*' => Http::response([])]);

    uazapi()->sendTyping('5511988887777', false);

    Http::assertSent(fn ($request) => $request['presence'] === 'paused'
        && ! array_key_exists('delay', $request->data()));
});

it('takes a message back from the conversation by its identifier alone', function (): void {
    Http::fake(['provider.test/*' => Http::response(['status' => 'Deleted'])]);

    uazapi()->deleteMessage('ENVIADA-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/message/delete')
        && $request['id'] === 'ENVIADA-1');
});

it('reports a deletion the provider refused', function (): void {
    Http::fake(['provider.test/*' => Http::response(['error' => 'Mensagem antiga demais.'], 400)]);

    expect(fn () => uazapi()->deleteMessage('ANTIGA-1'))->toThrow(ConnectorException::class);
});

it('omits the fields it has no value for', function (): void {
    Http::fake(['provider.test/*' => Http::response(['messageid' => 'ENVIADA-1'])]);

    $result = uazapi()->send(new OutgoingMessage(
        recipient: '5511988887777',
        body: 'Olá',
    ));

    expect($result->externalId)->toBe('ENVIADA-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/send/text')
        && $request['number'] === '5511988887777'
        && $request['text'] === 'Olá'
        && ! array_key_exists('replyid', $request->data()));
});

it('treats a provider that is down as worth another try', function (): void {
    Http::fake(['provider.test/*' => Http::response(['error' => 'Instância indisponível.'], 503)]);

    $result = uazapi()->send(new OutgoingMessage(recipient: '5511988887777', body: 'Olá'));

    expect($result->successful())->toBeFalse()
        ->and($result->retryable)->toBeTrue();
});

it('gives up straight away on a message the provider refuses', function (): void {
    Http::fake(['provider.test/*' => Http::response(['error' => 'Número inexistente.'], 400)]);

    expect(uazapi()->send(new OutgoingMessage(recipient: '000', body: 'Olá'))->retryable)->toBeFalse();
});

it('refuses a callback carrying the token of another instance', function (): void {
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->postJson("/api/webhooks/conectores/{$connection->id}", [
        'EventType' => 'messages',
        'token' => 'token-de-outra-instancia',
    ])->assertUnauthorized();
});

it('accepts a callback carrying the token we provisioned', function (): void {
    Queue::fake();

    ServiceQueue::factory()->create(['is_default' => true]);

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->postJson("/api/webhooks/conectores/{$connection->id}", [
        'EventType' => 'messages',
        'token' => 'instance-token',
        'message' => [
            'messageid' => 'MENSAGEM-1',
            'chatid' => '5511988887777@s.whatsapp.net',
            'sender' => '5511988887777@s.whatsapp.net',
            'senderName' => 'Cliente',
            'fromMe' => false,
            'messageType' => 'conversation',
            'text' => 'Bom dia',
            'messageTimestamp' => 1753500000000,
        ],
    ])->assertOk();

    Queue::assertPushed(ProcessConnectorEvent::class, 1);
});

it('normalises an inbound message the way the provider names its fields', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages',
        'token' => 'instance-token',
        'messages' => [
            [
                'messageid' => 'MENSAGEM-2',
                'chatid' => '120363000000000000@g.us',
                'sender' => '5511988887777@s.whatsapp.net',
                'senderName' => 'Cliente',
                'fromMe' => false,
                'isGroup' => true,
                'messageType' => 'imageMessage',
                'text' => 'Olha a foto',
                'fileURL' => 'https://mmg.whatsapp.net/foto.jpg',
                'quoted' => 'MENSAGEM-1',
                'messageTimestamp' => 1753500000000,
            ],
            ['messageid' => 'MENSAGEM-3', 'fromMe' => true, 'text' => 'Enviada por nós'],
        ],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $events = uazapi()->parseWebhook($request);

    expect($events)->toHaveCount(2)
        ->and($events[1]->externalId)->toBe('MENSAGEM-3')
        ->and($events[1]->fromMe)->toBeTrue();

    $message = $events[0];

    expect($message)->toBeInstanceOf(InboundMessage::class)
        ->and($message->externalId)->toBe('MENSAGEM-2')
        ->and($message->type)->toBe(MessageType::Image)
        ->and($message->body)->toBe('Olha a foto')
        ->and($message->mediaUrl)->toBe('https://mmg.whatsapp.net/foto.jpg')
        ->and($message->replyToExternalId)->toBe('MENSAGEM-1')
        ->and($message->contact->isGroup)->toBeTrue()
        ->and($message->contact->phone)->toBe('5511988887777')
        ->and($message->sentAt->timestamp)->toBe(1753500000);
});

function uazapiReceipt(array $event, ?string $state = null): Request
{
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(array_filter([
        'EventType' => 'messages_update',
        'token' => 'instance-token',
        'event' => $event,
        'state' => $state,
    ])));

    $request->headers->set('Content-Type', 'application/json');

    return $request;
}

it('turns the revocation receipt into the deletion of the message', function (): void {
    $events = uazapi()->parseWebhook(uazapiReceipt([
        'Chat' => '553492499140@s.whatsapp.net',
        'IsFromMe' => false,
        'IsGroup' => false,
        'MessageIDs' => ['3EB063ABC41D74ED5DB426'],
        'Sender' => '553492499140@s.whatsapp.net',
        'Timestamp' => '2026-07-30T13:35:56Z',
        'Type' => 'Deleted',
    ], 'Deleted'));

    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(DeliveryStatusUpdate::class)
        ->and($events[0]->externalId)->toBe('3EB063ABC41D74ED5DB426')
        ->and($events[0]->status)->toBe(MessageStatus::Deleted)
        ->and($events[0]->happenedAt->toIso8601String())->toBe('2026-07-30T13:35:56+00:00');
});

it('reads a receipt that speaks of several messages at once', function (): void {
    $events = uazapi()->parseWebhook(uazapiReceipt([
        'MessageIDs' => ['3EB02AB51741262937E0A9', '3EB06ADD4A6FC466640D2C'],
        'Timestamp' => 1785418567,
        'Type' => 'Delivered',
    ], 'Delivered'));

    expect($events)->toHaveCount(2)
        ->and(array_map(fn ($event): string => $event->externalId, $events))
        ->toBe(['3EB02AB51741262937E0A9', '3EB06ADD4A6FC466640D2C'])
        ->and($events[0]->status)->toBe(MessageStatus::Delivered)
        ->and($events[0]->happenedAt->timestamp)->toBe(1785418567);
});

it('reads the reading receipt the provider names in lower case', function (): void {
    $events = uazapi()->parseWebhook(uazapiReceipt([
        'MessageIDs' => ['3EB063ABC41D74ED5DB426'],
        'Timestamp' => '2026-07-30T13:35:55Z',
        'Type' => 'read',
    ], 'Read'));

    expect($events[0]->status)->toBe(MessageStatus::Read);
});

it('falls back to the state when the receipt carries no type', function (): void {
    $events = uazapi()->parseWebhook(uazapiReceipt([
        'MessageIDs' => ['3EB063ABC41D74ED5DB426'],
        'Timestamp' => 1785418567,
    ], 'Deleted'));

    expect($events[0]->status)->toBe(MessageStatus::Deleted);
});

it('normalises the inbound message the way the live instance sends it', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages',
        'token' => 'instance-token',
        'message' => [
            'chatid' => '553492499140@s.whatsapp.net',
            'chatlid' => '246539779895374@lid',
            'fromMe' => false,
            'isGroup' => false,
            'messageType' => 'Conversation',
            'messageTimestamp' => 1785418546000,
            'messageid' => '3EB063ABC41D74ED5DB426',
            'sender' => '246539779895374@lid',
            'senderName' => 'Higor Carneiro',
            'sender_pn' => '553492499140@s.whatsapp.net',
            'text' => 'Teste de mensagem apagada',
        ],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $message = uazapi()->parseWebhook($request)[0];

    expect($message)->toBeInstanceOf(InboundMessage::class)
        ->and($message->externalId)->toBe('3EB063ABC41D74ED5DB426')
        ->and($message->type)->toBe(MessageType::Text)
        ->and($message->body)->toBe('Teste de mensagem apagada')
        ->and($message->fromMe)->toBeFalse()
        ->and($message->contact->phone)->toBe('553492499140')
        ->and($message->contact->isGroup)->toBeFalse();
});

it('reads the delivery status by the name and by the acknowledgement of the provider', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages_update',
        'token' => 'instance-token',
        'messages' => [
            ['messageid' => 'M-1', 'status' => 'DELIVERY_ACK', 'timestamp' => 1753500000000],
            ['messageid' => 'M-2', 'status' => 'READ', 'timestamp' => 1753500000000],
            ['messageid' => 'M-3', 'status' => 'SERVER_ACK', 'timestamp' => 1753500000000],
            ['messageid' => 'M-4', 'status' => 'Queued', 'timestamp' => 1753500000000],
            ['messageid' => 'M-5', 'status' => 'Deleted', 'timestamp' => 1753500000000],
        ],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $statuses = array_map(
        fn ($event): MessageStatus => $event->status,
        uazapi()->parseWebhook($request),
    );

    expect($statuses)->toBe([
        MessageStatus::Delivered,
        MessageStatus::Read,
        MessageStatus::Sent,
        MessageStatus::Pending,
        MessageStatus::Deleted,
    ]);
});

it('registers the webhook with the events and the filters the provider names', function (): void {
    Http::fake([
        'provider.test/instance/create' => Http::response(['token' => 'instancia-123']),
        'provider.test/webhook' => Http::response([]),
    ]);

    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    app(ProvisionConnection::class)->handle($connection);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/webhook')
        && $request['events'] === ['messages', 'messages_update', 'connection']
        && $request['excludeMessages'] === ['wasSentByApi']);
});

it('reads the number from the chat when the contact hides it behind a lid', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages',
        'token' => 'instance-token',
        'messages' => [[
            'messageid' => 'ANONIMA-1',
            'chatid' => '553492499140@s.whatsapp.net',
            'sender' => '246539779895374@lid',
            'sender_lid' => '246539779895374@lid',
            'senderName' => 'Higor Carneiro',
            'fromMe' => false,
            'text' => 'Bom dia',
        ]],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $contact = uazapi()->parseWebhook($request)[0]->contact;

    expect($contact->identifier)->toBe('553492499140@s.whatsapp.net')
        ->and($contact->phone)->toBe('553492499140')
        ->and($contact->name)->toBe('Higor Carneiro');
});

it('keeps the group participant out of the number of the chat', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages',
        'token' => 'instance-token',
        'messages' => [[
            'messageid' => 'GRUPO-1',
            'chatid' => '120363000000000000@g.us',
            'sender' => '5511988887777@s.whatsapp.net',
            'isGroup' => true,
            'fromMe' => false,
            'text' => 'Oi pessoal',
        ]],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $contact = uazapi()->parseWebhook($request)[0]->contact;

    expect($contact->isGroup)->toBeTrue()
        ->and($contact->phone)->toBe('5511988887777');
});

it('reads the contact from the chat when the account answered from its own phone', function (): void {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
        'EventType' => 'messages',
        'token' => 'instance-token',
        'messages' => [[
            'messageid' => 'DO-CELULAR',
            'chatid' => '5511988887777@s.whatsapp.net',
            'sender' => '5511900000000@s.whatsapp.net',
            'senderName' => 'Minha Empresa',
            'senderProfilePicture' => 'https://mmg.whatsapp.net/eu.jpg',
            'fromMe' => true,
            'text' => 'Respondi pelo celular.',
        ]],
    ]));

    $request->headers->set('Content-Type', 'application/json');

    $message = uazapi()->parseWebhook($request)[0];

    expect($message->fromMe)->toBeTrue()
        ->and($message->contact->identifier)->toBe('5511988887777@s.whatsapp.net')
        ->and($message->contact->phone)->toBe('5511988887777')
        ->and($message->contact->name)->toBeNull()
        ->and($message->contact->avatarUrl)->toBeNull();
});
