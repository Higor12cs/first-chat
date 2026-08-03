<?php

use App\Jobs\Messaging\ProcessConnectorEvent;
use App\Models\ChannelConnection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Queue::fake();
    tenant();

    $this->connection = ChannelConnection::factory()->create([
        'driver' => 'uazapi',
        'credentials' => ['token' => 'token-da-instancia'],
        'is_active' => true,
    ]);
});

function callback(array $headers = []): TestResponse
{
    return test()->postJson(
        route('webhooks.connector', ['connection' => test()->connection->id]),
        ['EventType' => 'messages', 'messages' => []],
        $headers,
    );
}

it('accepts a callback carrying the instance token', function (): void {
    callback(['token' => 'token-da-instancia'])->assertOk();
});

it('refuses a callback carrying the wrong token', function (): void {
    callback(['token' => 'token-errado'])->assertUnauthorized();
});

it('refuses an anonymous callback when no secret is configured', function (): void {
    config()->set('connectors.webhook_secret', null);

    callback()->assertUnauthorized();
});

it('refuses an anonymous callback that guesses the secret wrong', function (): void {
    config()->set('connectors.webhook_secret', 'segredo-real');

    callback(['X-Webhook-Secret' => 'chute'])->assertUnauthorized();
});

it('accepts an anonymous callback holding the configured secret', function (): void {
    config()->set('connectors.webhook_secret', 'segredo-real');

    callback(['X-Webhook-Secret' => 'segredo-real'])->assertOk();
});

it('keeps a refused callback from reaching the queue', function (): void {
    config()->set('connectors.webhook_secret', null);

    callback()->assertUnauthorized();

    Queue::assertNotPushed(ProcessConnectorEvent::class);
});

it('ignores a callback for a connection that was turned off', function (): void {
    $this->connection->forceFill(['is_active' => false])->save();

    callback(['token' => 'token-da-instancia'])->assertNotFound();
});
