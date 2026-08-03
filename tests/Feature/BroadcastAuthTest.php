<?php

use App\Http\Middleware\IdentifyTenant;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'chave',
        'secret' => 'segredo',
        'app_id' => 'app',
        'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
    ]);

    require base_path('routes/channels.php');
});
it('authorizes the channel the conversation list listens to', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$tenant->id}.conversations", 'socket_id' => '123.456'])
        ->assertOk();
});

it('authorizes the channel a single conversation listens to', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);
    $conversation = Conversation::factory()->create();

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-conversations.{$conversation->id}", 'socket_id' => '123.456'])
        ->assertOk();
});

it('authorizes the channel the connections page listens to', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$tenant->id}.connections", 'socket_id' => '123.456'])
        ->assertOk();
});

it('refuses the channels of another tenant', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $other = '01aaaaaaaaaaaaaaaaaaaaaaaa';

    $this->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$other}.conversations", 'socket_id' => '123.456'])
        ->assertForbidden();
});

it('refuses the channels of a tenant the user is not attending right now', function (): void {
    $first = tenant();
    $user = userFor($first);

    $second = Tenant::factory()->create();
    membershipFor($user, $second);

    $this->actingAs($user)
        ->withSession([IdentifyTenant::TENANT_KEY => $first->id])
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$second->id}.conversations", 'socket_id' => '123.456'])
        ->assertForbidden();
});

it('authorizes the workspace channel of a platform administrator', function (): void {
    $tenant = tenant();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->withSession([IdentifyTenant::TENANT_KEY => $tenant->id])
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$tenant->id}.conversations", 'socket_id' => '123.456'])
        ->assertOk();
});

it('refuses every tenant channel to an administrator without a workspace', function (): void {
    $tenant = tenant();
    $admin = User::factory()->superAdmin()->create();

    $this->flushSession();

    $this->actingAs($admin)
        ->postJson('/broadcasting/auth', ['channel_name' => "private-tenants.{$tenant->id}.conversations", 'socket_id' => '123.456'])
        ->assertForbidden();
});

it('refuses a conversation the restricted user cannot see in the inbox', function (): void {
    $tenant = tenant();
    $agent = userFor($tenant);
    $restricted = userFor($tenant, null, ['hides_other_conversations' => true]);

    $conversation = Conversation::factory()->create([
        'assigned_user_id' => $agent->id,
        'is_group' => false,
    ]);

    $this->actingAs($restricted)
        ->withSession([IdentifyTenant::TENANT_KEY => $tenant->id])
        ->postJson('/broadcasting/auth', ['channel_name' => "private-conversations.{$conversation->id}", 'socket_id' => '123.456'])
        ->assertForbidden();
});

it('refuses a conversation of a tenant outside the current session', function (): void {
    $first = tenant();
    $user = userFor($first);

    $second = tenant();
    membershipFor($user, $second);
    $foreign = Conversation::factory()->create();

    $this->actingAs($user)
        ->withSession([IdentifyTenant::TENANT_KEY => $first->id])
        ->postJson('/broadcasting/auth', ['channel_name' => "private-conversations.{$foreign->id}", 'socket_id' => '123.456'])
        ->assertForbidden();
});
