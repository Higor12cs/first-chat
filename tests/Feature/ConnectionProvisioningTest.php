<?php

use App\Actions\Messaging\ProvisionConnection;
use App\Actions\Messaging\ProvisionTenantConnections;
use App\Actions\Tenancy\CreateTenant;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\Messaging\ProvisionConnection as ProvisionConnectionJob;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    config()->set('connectors.drivers.uazapi.credentials', [
        'base_url' => 'https://provider.test',
        'admin_token' => 'admin-token',
    ]);
});

it('gives a new tenant the connections it contracted', function (): void {
    $tenant = app(CreateTenant::class)->handle(
        name: 'Acme',
        ownerName: 'Dona da Conta',
        ownerEmail: 'dona@example.com',
        ownerPassword: 'senha-secreta',
        maxConnections: 3,
    );

    $connections = ChannelConnection::query()
        ->acrossTenants()
        ->where('tenant_id', $tenant->id)
        ->orderBy('id')
        ->get();

    expect($connections)->toHaveCount(3)
        ->and($connections->pluck('channel')->unique()->all())->toBe([Channel::WhatsApp])
        ->and($connections->pluck('name')->all())->toBe(['WhatsApp', 'WhatsApp 2', 'WhatsApp 3']);

    Queue::assertPushed(ProvisionConnectionJob::class, 3);
});

it('gives a single connection when the tenant sets no ceiling', function (): void {
    $tenant = Tenant::factory()->create(['max_connections' => null]);

    app(ProvisionTenantConnections::class)->handle($tenant);

    expect(ChannelConnection::query()->acrossTenants()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('reads the ceiling the administrator set on the tenant', function (): void {
    $tenant = Tenant::factory()->create(['max_connections' => 2]);

    app(ProvisionTenantConnections::class)->handle($tenant);

    expect(ChannelConnection::query()->acrossTenants()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('only fills the gap when it runs again', function (): void {
    $tenant = Tenant::factory()->create(['max_connections' => 2]);

    $provision = app(ProvisionTenantConnections::class);

    expect($provision->handle($tenant))->toHaveCount(2)
        ->and($provision->handle($tenant))->toHaveCount(0)
        ->and(ChannelConnection::query()->acrossTenants()->where('tenant_id', $tenant->id)->count())->toBe(2);
});

it('provisions the extra connections a raised ceiling grants', function (): void {
    $tenant = Tenant::factory()->create(['max_connections' => 1]);
    app(ProvisionTenantConnections::class)->handle($tenant);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->put("/admin/tenants/{$tenant->id}", [
            'name' => $tenant->name,
            'status' => 'active',
            'max_connections' => 3,
        ])
        ->assertRedirect();

    expect(ChannelConnection::query()->acrossTenants()->where('tenant_id', $tenant->id)->count())->toBe(3);
});

it('stores the instance token the provider returns', function (): void {
    Http::fake(['provider.test/*' => Http::response(['token' => 'instancia-123'])]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    expect(app(ProvisionConnection::class)->handle($connection))->toBeTrue()
        ->and($connection->fresh()->credential('token'))->toBe('instancia-123');
});

it('does not provision twice', function (): void {
    Http::fake(['provider.test/*' => Http::response(['token' => 'outro-token'])]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'ja-existe']]);

    app(ProvisionConnection::class)->handle($connection);

    expect($connection->fresh()->credential('token'))->toBe('ja-existe');

    Http::assertNothingSent();
});

it('reports failure without breaking when the provider is down', function (): void {
    Http::fake(['provider.test/*' => Http::response('indisponível', 503)]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    expect(app(ProvisionConnection::class)->handle($connection))->toBeFalse()
        ->and($connection->fresh()->credential('token'))->toBeNull()
        ->and($connection->fresh()->last_error)->not->toBeNull();
});

it('keeps the provider name out of what the tenant reads', function (): void {
    Http::fake(['provider.test/*' => Http::response('boom', 500)]);

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    $response = $this->actingAs($user)->post("/conexoes/{$connection->id}/conectar");

    $error = $response->assertRedirect()->getSession()->get('error');

    expect($error)->not->toBeNull()
        ->and(strtolower($error))->not->toContain('uazapi')
        ->and(strtolower($error))->not->toContain('provider.test');
});

it('creates the tenant even when the provider cannot be reached', function (): void {
    Http::fake(['provider.test/*' => Http::response('down', 503)]);

    $tenant = app(CreateTenant::class)->handle(
        name: 'Resiliente',
        ownerName: 'Dona',
        ownerEmail: 'resiliente@example.com',
        ownerPassword: 'senha-secreta',
        maxConnections: 1,
    );

    expect($tenant->exists)->toBeTrue()
        ->and(ChannelConnection::query()->acrossTenants()->where('tenant_id', $tenant->id)->count())->toBe(1);

    app(TenantContext::class)->forget();
});

it('points the provider at our webhook while provisioning', function (): void {
    Http::fake([
        'provider.test/instance/create' => Http::response(['token' => 'instancia-123']),
        'provider.test/webhook' => Http::response(['ok' => true]),
    ]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    app(ProvisionConnection::class)->handle($connection);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/webhook')
        && $request['url'] === $connection->webhookUrl()
        && $request['enabled'] === true);
});

it('keeps the instance when the provider refuses the webhook', function (): void {
    Http::fake([
        'provider.test/instance/create' => Http::response(['token' => 'instancia-123']),
        'provider.test/webhook' => Http::response('nope', 500),
    ]);

    $tenant = tenant();
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    expect(app(ProvisionConnection::class)->handle($connection))->toBeTrue()
        ->and($connection->fresh()->credential('token'))->toBe('instancia-123');
});

it('names the instance with the configured prefix, the tenant and a random suffix', function (): void {
    config()->set('connectors.instance_prefix', 'first-chat');

    $tenant = tenant(['name' => 'Padaria do João']);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    expect($connection->instanceName())->toMatch('/^first-chat-padaria-do-joao-[a-z0-9]{8}$/')
        ->and($connection->instanceName())->not->toBe($connection->instanceName());
});

it('sends the generated name to the provider and keeps it for support', function (): void {
    config()->set('connectors.instance_prefix', 'first-chat');

    Http::fake([
        'provider.test/instance/create' => Http::response(['token' => 'instancia-123']),
        'provider.test/webhook' => Http::response(['ok' => true]),
    ]);

    $tenant = tenant(['name' => 'Acme']);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    app(ProvisionConnection::class)->handle($connection);

    $name = $connection->fresh()->credential('instance_name');

    expect($name)->toMatch('/^first-chat-acme-[a-z0-9]{8}$/');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && $request['name'] === $name);
});

it('stamps the instance with the app prefix so reconciliation can find it', function (): void {
    config()->set('connectors.instance_prefix', 'first-chat');

    Http::fake([
        'provider.test/instance/create' => Http::response(['token' => 'instancia-123', 'instance' => ['id' => 'inst-1']]),
        'provider.test/webhook' => Http::response(['ok' => true]),
    ]);

    $tenant = tenant(['name' => 'Acme']);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => []]);

    app(ProvisionConnection::class)->handle($connection);

    expect($connection->fresh()->credential('instance_id'))->toBe('inst-1');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/instance/create')
        && $request['adminField01'] === 'first-chat'
        && filled($request['adminField02']));
});

it('sends the user to the page that shows the qr code', function (): void {
    Http::fake(['provider.test/*' => Http::response([
        'instance' => ['status' => 'connecting', 'qrcode' => 'data:image/png;base64,iVBORw0KGgo='],
    ])]);

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)
        ->from('/conexoes')
        ->post("/conexoes/{$connection->id}/conectar")
        ->assertRedirect("/conexoes/{$connection->id}");

    expect($connection->fresh()->qr_code)->toBe('data:image/png;base64,iVBORw0KGgo=');
});

it('keeps the user where they were when the channel does not answer', function (): void {
    Http::fake(['provider.test/*' => Http::response('boom', 500)]);

    $tenant = tenant();
    $user = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi', 'credentials' => ['token' => 'instance-token']]);

    $this->actingAs($user)
        ->from('/conexoes')
        ->post("/conexoes/{$connection->id}/conectar")
        ->assertRedirect('/conexoes');
});

it('honours a different prefix', function (): void {
    config()->set('connectors.instance_prefix', 'staging');

    $tenant = tenant(['name' => 'Acme']);
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi']);

    expect($connection->instanceName())->toStartWith('staging-acme-');
});
