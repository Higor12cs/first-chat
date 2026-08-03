<?php

use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Events\Messaging\ConnectorStatusChanged;
use App\Jobs\Messaging\SyncConnectionStatus;
use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);
});

it('warns on every page while a connection is down', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    ChannelConnection::factory()->create([
        'name' => 'WhatsApp',
        'status' => ConnectionStatus::Disconnected,
        'last_error' => 'sessão encerrada no aparelho',
    ]);

    $this->actingAs($user)
        ->get('/painel')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.level', 'danger')
            ->where('alerts.0.href', '/conexoes')
            ->where('alerts.0.title', 'WhatsApp desconectado')
            ->where('alerts.0.message', 'Novas mensagens não serão entregues até reconectar.'));
});

it('stays quiet while every connection answers', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);

    $this->actingAs($user)
        ->get('/painel')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts', 0));
});

it('keeps the link out of reach of who cannot open connections', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, ['conversations.view']);

    ChannelConnection::factory()->create(['status' => ConnectionStatus::Disconnected]);

    $this->actingAs($user)
        ->get('/painel')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.href', null));
});

it('counts the messages the provider refused', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    ChannelConnection::factory()->create(['status' => ConnectionStatus::Connected]);
    $conversation = Conversation::factory()->create();

    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Outbound,
        'status' => MessageStatus::Failed,
    ]);

    $this->actingAs($user)
        ->get('/painel')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.id', 'messages-failed')
            ->where('alerts.0.title', '2 mensagens não enviadas'));
});

it('notices a disconnection made on the phone without anyone asking', function (): void {
    Event::fake([ConnectorStatusChanged::class]);
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'disconnected']])]);

    $tenant = tenant();

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Connected,
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->artisan('connections:sync')->assertSuccessful();

    expect($connection->fresh()->status)->toBe(ConnectionStatus::Disconnected);

    Event::assertDispatched(ConnectorStatusChanged::class);
});

it('leaves a connection still being paired alone', function (): void {
    Http::fake();

    $tenant = tenant();

    ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Connecting,
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->artisan('connections:sync')->assertSuccessful();

    Http::assertNothingSent();
});

it('reads the provider on the queue instead of holding the scheduler', function (): void {
    Queue::fake();

    $tenant = tenant();

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'status' => ConnectionStatus::Connected,
        'credentials' => ['token' => 'instance-token'],
    ]);

    $this->artisan('connections:sync')->assertSuccessful();

    Http::assertNothingSent();

    Queue::assertPushed(
        SyncConnectionStatus::class,
        fn (SyncConnectionStatus $job): bool => $job->channelConnection->is($connection)
            && $job->queue === 'maintenance',
    );
});
