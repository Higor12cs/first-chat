<?php

use App\Actions\Messaging\ImportConnectionGroups;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Jobs\Messaging\SyncConnectionGroups;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);

    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
});

it('opens a conversation from a typed number', function (): void {
    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);

    $this->actingAs($this->user)
        ->post('/atendimentos', ['phone' => '5511988887777', 'name' => 'Cliente Novo'])
        ->assertRedirect();

    $conversation = Conversation::query()->latest('id')->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->assigned_user_id)->toBe($this->user->id)
        ->and($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->contactChannel->identifier)->toBe('5511988887777@s.whatsapp.net')
        ->and($conversation->contact->phone)->toBe('5511988887777');
});

it('strips whatever punctuation the agent typed', function (): void {
    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);

    $this->actingAs($this->user)
        ->post('/atendimentos', ['phone' => '55 (11) 98888-7777'])
        ->assertRedirect();

    expect(Conversation::query()->latest('id')->first()->contactChannel->identifier)
        ->toBe('5511988887777@s.whatsapp.net');
});

it('reuses the open conversation of the same number', function (): void {
    $connection = ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);
    $contact = Contact::factory()->create(['phone' => '5511988887777']);
    $channel = ContactChannel::factory()->create([
        'contact_id' => $contact->id,
        'channel_connection_id' => $connection->id,
        'identifier' => '5511988887777@s.whatsapp.net',
    ]);
    $existing = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
    ]);

    $this->actingAs($this->user)
        ->post('/atendimentos', ['contact_id' => $contact->id])
        ->assertRedirect("/atendimentos/{$existing->id}");

    expect(Conversation::query()->count())->toBe(1);
});

it('opens the conversation of another agent without taking it', function (): void {
    $connection = ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);
    $other = userFor($this->tenant);
    $contact = Contact::factory()->create(['name' => 'Joana Ribeiro', 'phone' => '5511988887777']);
    $channel = ContactChannel::factory()->create([
        'contact_id' => $contact->id,
        'channel_connection_id' => $connection->id,
        'identifier' => '5511988887777@s.whatsapp.net',
    ]);
    $existing = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
        'assigned_user_id' => $other->id,
    ]);

    $this->actingAs($this->user)
        ->post('/atendimentos', ['contact_id' => $contact->id])
        ->assertRedirect("/atendimentos/{$existing->id}")
        ->assertSessionHas('warning', "Este contato já está em atendimento por {$other->name}.");

    expect($existing->fresh()->assigned_user_id)->toBe($other->id);
});

it('opens the conversation on the channel the agent picked', function (): void {
    ChannelConnection::factory()->create(['name' => 'Comercial', 'status' => ConnectionStatus::Connected]);
    $support = ChannelConnection::factory()->create(['name' => 'Suporte', 'status' => ConnectionStatus::Connected]);

    $this->actingAs($this->user)
        ->post('/atendimentos', [
            'phone' => '5511988887777',
            'channel_connection_id' => $support->id,
        ])
        ->assertRedirect();

    expect(Conversation::query()->latest('id')->first()->channel_connection_id)->toBe($support->id);
});

it('rejects a channel that is turned off', function (): void {
    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);
    $inactive = ChannelConnection::factory()->create(['is_active' => false]);

    $this->actingAs($this->user)
        ->post('/atendimentos', [
            'phone' => '5511988887777',
            'channel_connection_id' => $inactive->id,
        ])
        ->assertSessionHasErrors('channel_connection_id');
});

it('says who is handling the contact instead of opening a conversation out of reach', function (): void {
    $connection = ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);
    $other = userFor($this->tenant);
    $restricted = userFor($this->tenant, ['conversations.view', 'conversations.reply']);
    $queue = ServiceQueue::factory()->create(['name' => 'Financeiro', 'is_default' => true]);

    $contact = Contact::factory()->create(['name' => 'Joana Ribeiro', 'phone' => '5511988887777']);
    $channel = ContactChannel::factory()->create([
        'contact_id' => $contact->id,
        'channel_connection_id' => $connection->id,
        'identifier' => '5511988887777@s.whatsapp.net',
    ]);
    $existing = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
        'assigned_user_id' => $other->id,
        'service_queue_id' => $queue->id,
    ]);

    $this->actingAs($restricted)
        ->post('/atendimentos', ['contact_id' => $contact->id])
        ->assertRedirect('/atendimentos')
        ->assertSessionHas('warning', "Este contato já está em atendimento por {$other->name} no setor Financeiro. Peça a transferência para continuar.");

    expect($existing->fresh()->assigned_user_id)->toBe($other->id)
        ->and(Conversation::query()->count())->toBe(1);
});

it('refuses to start without a number', function (): void {
    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);

    $this->actingAs($this->user)
        ->post('/atendimentos', ['phone' => '---'])
        ->assertSessionHasErrors('phone');
});

it('lists contacts that have a phone', function (): void {
    Contact::factory()->create(['name' => 'Joana Ribeiro', 'phone' => '5511988887777']);
    Contact::factory()->create(['name' => 'Sem Telefone', 'phone' => null]);

    $this->actingAs($this->user)
        ->getJson('/atendimentos/contatos?busca=Joana')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Joana Ribeiro');
});

it('takes over a conversation of another agent', function (): void {
    $other = userFor($this->tenant);
    $queue = ServiceQueue::factory()->create(['is_default' => true]);
    $conversation = Conversation::factory()->create([
        'assigned_user_id' => $other->id,
        'service_queue_id' => $queue->id,
    ]);

    $this->actingAs($this->user)
        ->post("/atendimentos/{$conversation->id}/assumir")
        ->assertRedirect();

    expect($conversation->fresh()->assigned_user_id)->toBe($this->user->id)
        ->and($conversation->fresh()->status)->toBe(ConversationStatus::Open);
});

it('asks for the groups of the account once the pairing succeeds', function (): void {
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'connected']])]);

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Disconnected,
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->actingAs($this->user)
        ->post("/conexoes/{$connection->id}/conectar")
        ->assertRedirect();

    Queue::assertPushed(SyncConnectionGroups::class);
});

it('opens a conversation for every group of the account', function (): void {
    Http::fake(['provider.test/group/list*' => Http::response(['groups' => [
        ['JID' => '120363000000000001@g.us', 'Name' => 'Equipe Comercial'],
        ['JID' => '120363000000000002@g.us', 'Name' => 'Avisos'],
        ['Name' => 'Sem Identificador'],
    ]])]);

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Connected,
        'credentials' => ['token' => 'instance-token'],
    ]);

    expect(app(ImportConnectionGroups::class)->handle($connection))->toBe(2);

    $groups = Conversation::query()->where('is_group', true)->with('contact')->get();

    expect($groups)->toHaveCount(2)
        ->and($groups->pluck('contact.name')->sort()->values()->all())->toBe(['Avisos', 'Equipe Comercial']);
});

it('does not duplicate the groups when it runs again', function (): void {
    Http::fake(['provider.test/group/list*' => Http::response(['groups' => [
        ['JID' => '120363000000000001@g.us', 'Name' => 'Equipe Comercial'],
    ]])]);

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Connected,
        'credentials' => ['token' => 'instance-token'],
    ]);

    app(ImportConnectionGroups::class)->handle($connection);
    app(ImportConnectionGroups::class)->handle($connection);

    expect(Conversation::query()->where('is_group', true)->count())->toBe(1);
});
