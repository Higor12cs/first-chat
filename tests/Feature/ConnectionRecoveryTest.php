<?php

use App\Jobs\Messaging\ProvisionConnection as ProvisionConnectionJob;
use App\Jobs\Messaging\SyncConnectionStatus;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    tenant();

    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);

    $this->connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'token-que-morreu'],
    ]);
});

function syncStatus(): void
{
    app(SyncConnectionStatus::class, ['channelConnection' => test()->connection])
        ->handle(app(ConnectorManager::class), app(ConnectionStatusSynchronizer::class));
}

it('drops a credential the provider no longer recognises', function (): void {
    Http::fake(['provider.test/*' => Http::response(['message' => 'Invalid token.'], 401)]);

    syncStatus();

    expect($this->connection->fresh()->credential('token'))->toBeNull();
});

it('asks for a new instance after dropping the dead credential', function (): void {
    Http::fake(['provider.test/*' => Http::response(['message' => 'Invalid token.'], 401)]);

    syncStatus();

    Queue::assertPushed(ProvisionConnectionJob::class);
});

it('keeps a working credential when the provider is merely unavailable', function (): void {
    Http::fake(['provider.test/*' => Http::response('indisponível', 503)]);

    syncStatus();

    expect($this->connection->fresh()->credential('token'))->toBe('token-que-morreu');

    Queue::assertNotPushed(ProvisionConnectionJob::class);
});

it('leaves a healthy connection alone', function (): void {
    Http::fake(['provider.test/*' => Http::response(['instance' => ['status' => 'connected', 'owner' => '5511999999999']])]);

    syncStatus();

    expect($this->connection->fresh()->credential('token'))->toBe('token-que-morreu');

    Queue::assertNotPushed(ProvisionConnectionJob::class);
});
