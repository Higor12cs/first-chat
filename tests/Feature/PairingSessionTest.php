<?php

use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Jobs\Messaging\RefreshPairingSession;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);
});

function connecting(array $instance = ['status' => 'connecting', 'qrcode' => 'data:image/png;base64,PRIMEIRO']): void
{
    Http::fake(['provider.test/*' => Http::response(['instance' => $instance])]);
}

it('pairs by phone when the user gives a number', function (): void {
    Queue::fake();
    connecting(['status' => 'connecting', 'paircode' => 'WZTK9QLM']);

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)
        ->post("/conexoes/{$connection->id}/conectar", ['phone' => '(11) 98888-7777'])
        ->assertRedirect("/conexoes/{$connection->id}");

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && $request['phone'] === '11988887777');

    expect($connection->fresh()->pair_code)->toBe('WZTK9QLM');
});

it('refuses a number that is not a phone', function (): void {
    connecting();

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)
        ->from("/conexoes/{$connection->id}")
        ->post("/conexoes/{$connection->id}/conectar", ['phone' => '123'])
        ->assertSessionHasErrors('phone');

    Http::assertNothingSent();
});

it('asks for a qr code when no number is given', function (): void {
    Queue::fake();
    connecting();

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)->post("/conexoes/{$connection->id}/conectar")->assertRedirect();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/connect')
        && ! array_key_exists('phone', $request->data()));

    expect($connection->fresh()->qr_code)->toBe('data:image/png;base64,PRIMEIRO');
});

it('queues the renewal that keeps the code alive', function (): void {
    Queue::fake();
    connecting();

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)->post("/conexoes/{$connection->id}/conectar");

    Queue::assertPushed(RefreshPairingSession::class, 1);
});

it('does not queue a renewal when the pairing did not open', function (): void {
    Queue::fake();
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'connected', 'owner' => '5511988887777']])]);

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)->post("/conexoes/{$connection->id}/conectar");

    Queue::assertNotPushed(RefreshPairingSession::class);
});

it('renews the code and schedules the next read', function (): void {
    Queue::fake();
    connecting(['status' => 'connecting', 'qrcode' => 'data:image/png;base64,SEGUNDO']);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
        'status' => ConnectionStatus::Connecting,
        'qr_code' => 'data:image/png;base64,PRIMEIRO',
    ]);

    (new RefreshPairingSession($connection))->handle(
        app(ConnectorManager::class),
        app(ConnectionStatusSynchronizer::class),
    );

    expect($connection->fresh()->qr_code)->toBe('data:image/png;base64,SEGUNDO');

    Queue::assertPushed(RefreshPairingSession::class, 1);
});

it('stops renewing once the number is paired', function (): void {
    Queue::fake();
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'connected', 'owner' => '5511988887777']])]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
        'status' => ConnectionStatus::Connecting,
        'qr_code' => 'data:image/png;base64,PRIMEIRO',
    ]);

    (new RefreshPairingSession($connection))->handle(
        app(ConnectorManager::class),
        app(ConnectionStatusSynchronizer::class),
    );

    $connection = $connection->fresh();

    expect($connection->status)->toBe(ConnectionStatus::Connected)
        ->and($connection->qr_code)->toBeNull()
        ->and($connection->pair_code)->toBeNull();

    Queue::assertNotPushed(RefreshPairingSession::class);
});

it('gives up after the window the provider allows', function (): void {
    Queue::fake();
    connecting();

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
        'status' => ConnectionStatus::Connecting,
    ]);

    (new RefreshPairingSession($connection, RefreshPairingSession::MAX_ATTEMPTS))->handle(
        app(ConnectorManager::class),
        app(ConnectionStatusSynchronizer::class),
    );

    Queue::assertNotPushed(RefreshPairingSession::class);
});

it('stops renewing when the connection left the pairing state', function (): void {
    Queue::fake();
    Http::fake();

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
        'status' => ConnectionStatus::Disconnected,
    ]);

    (new RefreshPairingSession($connection))->handle(
        app(ConnectorManager::class),
        app(ConnectionStatusSynchronizer::class),
    );

    Http::assertNothingSent();
    Queue::assertNotPushed(RefreshPairingSession::class);
});
