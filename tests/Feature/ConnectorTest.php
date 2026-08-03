<?php

use App\Domain\Messaging\Connectors\Uazapi\UazapiConnector;
use App\Domain\Messaging\Contracts\ManagesSession;
use App\Domain\Messaging\Contracts\MessagingConnector;
use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use App\Services\Messaging\ConnectorRegistry;

it('resolves every configured driver to a messaging connector', function (): void {
    tenant();

    $registry = app(ConnectorRegistry::class);
    $manager = app(ConnectorManager::class);

    foreach ($registry->all() as $definition) {
        $connection = ChannelConnection::factory()->create([
            'driver' => $definition->key,
            'channel' => $definition->channel,
        ]);

        expect($manager->for($connection))->toBeInstanceOf(MessagingConnector::class);
    }
});

it('keeps the driver credentials on the platform, never on the tenant', function (): void {
    tenant();

    $connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'instance-token'],
    ]);

    expect($connection->definition()->credentials)->toHaveKey('base_url')
        ->and($connection->credential('base_url'))->toBeNull()
        ->and($connection->credential('token'))->toBe('instance-token')
        ->and($connection->toArray())->not->toHaveKey('credentials');
});

it('names a provisioning driver for every channel a tenant receives', function (): void {
    $registry = app(ConnectorRegistry::class);

    expect($registry->tenantChannels())->not->toBeEmpty();

    foreach ($registry->tenantChannels() as $channel) {
        expect($registry->provisioningDriver($channel))->not->toBeNull();
    }
});

it('marks the WhatsApp driver as session based', function (): void {
    tenant();

    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi']);

    $connector = app(ConnectorManager::class)->for($connection);

    expect($connector)->toBeInstanceOf(UazapiConnector::class)
        ->and($connector)->toBeInstanceOf(ManagesSession::class);
});

it('offers WhatsApp as the only channel', function (): void {
    $registry = app(ConnectorRegistry::class);

    expect($registry->all()->keys()->all())->toBe(['uazapi'])
        ->and($registry->tenantChannels())->toBe([Channel::WhatsApp])
        ->and(Channel::cases())->toBe([Channel::WhatsApp]);
});

it('refuses a webhook for an inactive connection', function (): void {
    tenant();

    $connection = ChannelConnection::factory()->create(['is_active' => false]);

    $this->postJson("/api/webhooks/conectores/{$connection->id}", [])->assertNotFound();
});

it('broadcasts a status change without tripping the queue', function (): void {
    tenant();

    $connection = ChannelConnection::factory()->create([
        'status' => ConnectionStatus::Disconnected,
    ]);

    app(ConnectionStatusSynchronizer::class)->apply($connection, new ConnectionStatusUpdate(
        status: ConnectionStatus::Connected,
        externalIdentifier: '5511988887777',
    ));

    $connection = $connection->fresh();

    expect($connection->status)->toBe(ConnectionStatus::Connected)
        ->and($connection->external_identifier)->toBe('5511988887777')
        ->and($connection->last_connected_at)->not->toBeNull();
});

it('drops the qr code and the error once the pairing succeeds', function (): void {
    tenant();

    $connection = ChannelConnection::factory()->create([
        'status' => ConnectionStatus::Connecting,
        'qr_code' => 'data:image/png;base64,iVBORw0KGgo=',
        'last_error' => 'Falha na comunicação com o conector.',
    ]);

    $synchronizer = app(ConnectionStatusSynchronizer::class);

    $synchronizer->apply($connection, new ConnectionStatusUpdate(
        status: ConnectionStatus::Connected,
        externalIdentifier: '5511988887777',
    ));

    expect($connection->fresh()->qr_code)->toBeNull()
        ->and($connection->fresh()->last_error)->toBeNull();

    $synchronizer->apply($connection, new ConnectionStatusUpdate(status: ConnectionStatus::Disconnected));

    expect($connection->fresh()->external_identifier)->toBe('5511988887777')
        ->and($connection->fresh()->last_connected_at)->not->toBeNull();
});
