<?php

use App\Models\ChannelConnection;

beforeEach(function (): void {
    tenant();
});

it('sends the provider to the tunnel when one is configured', function (): void {
    config()->set('connectors.public_url', 'https://unplanted-abstract-unburned.ngrok-free.dev');

    $connection = ChannelConnection::factory()->create();

    expect($connection->webhookUrl())
        ->toStartWith('https://unplanted-abstract-unburned.ngrok-free.dev/')
        ->toContain($connection->id);
});

it('ignores a trailing slash on the configured address', function (): void {
    config()->set('connectors.public_url', 'https://tunel.example.com/');

    $connection = ChannelConnection::factory()->create();

    expect($connection->webhookUrl())->toStartWith('https://tunel.example.com/')
        ->not->toContain('.com//');
});

it('falls back to the application address when no tunnel is set', function (): void {
    config()->set('connectors.public_url', null);

    $connection = ChannelConnection::factory()->create();

    expect($connection->webhookUrl())->toBe(route('webhooks.connector', ['connection' => $connection->id]));
});

it('keeps the route reachable at the address it announces', function (): void {
    config()->set('connectors.public_url', 'https://tunel.example.com');

    $connection = ChannelConnection::factory()->create();

    $path = parse_url($connection->webhookUrl(), PHP_URL_PATH);

    expect($path)->toBe(parse_url(route('webhooks.connector', ['connection' => $connection->id]), PHP_URL_PATH));
});
