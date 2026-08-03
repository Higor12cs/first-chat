<?php

use App\Http\Middleware\IdentifyTenant;
use App\Models\AiInteraction;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiCostCalculator;

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
});

it('renders the platform pages for a super admin', function (string $url, string $component): void {
    Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/admin/tenants', 'Admin/Tenants/Index'],
    ['/admin/usuarios', 'Admin/Users/Index'],
    ['/admin/uso', 'Admin/Usage/Index'],
    ['/admin/auditoria', 'Admin/AuditLogs/Index'],
]);

it('keeps the platform pages away from a tenant user', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)->get('/admin/usuarios')->assertForbidden();
    $this->actingAs($user)->get('/admin/uso')->assertForbidden();
});

it('lists users of every tenant', function (): void {
    $first = tenant(['name' => 'Alfa']);
    $second = Tenant::factory()->create(['name' => 'Beta']);

    userFor($first, null, ['name' => 'Ana']);
    userFor($second, null, ['name' => 'Bruno']);

    $this->actingAs($this->admin)
        ->get('/admin/usuarios')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 3));
});

it('creates a user attached to many tenants', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/usuarios', [
            'name' => 'Carla',
            'email' => 'carla@example.com',
            'password' => 'senha-secreta',
            'tenant_ids' => [$first->id, $second->id],
        ])
        ->assertRedirect();

    $carla = User::query()->acrossTenants()->where('email', 'carla@example.com')->firstOrFail();

    expect($carla->tenants()->pluck('tenants.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id]);
});

it('drops roles and queues when a user leaves a tenant', function (): void {
    $origin = tenant();
    $user = userFor($origin);
    $queue = ServiceQueue::factory()->create();
    $user->serviceQueues()->sync([$queue->id]);

    expect($user->roles()->count())->toBe(1)
        ->and($user->serviceQueues()->count())->toBe(1);

    $destination = Tenant::factory()->create();

    $this->actingAs($this->admin)
        ->put("/admin/usuarios/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'tenant_ids' => [$destination->id],
        ])
        ->assertRedirect();

    $user = User::query()->acrossTenants()->findOrFail($user->id);

    expect($user->tenants()->pluck('tenants.id')->all())->toBe([$destination->id])
        ->and($user->roles()->acrossTenants()->count())->toBe(0)
        ->and($user->serviceQueues()->acrossTenants()->count())->toBe(0);
});

it('lets the platform administrator enter and leave a tenant workspace', function (): void {
    $tenant = tenant();
    $conversation = Conversation::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/tenants/{$tenant->id}/acessar")
        ->assertRedirect('/atendimentos')
        ->assertSessionHas(IdentifyTenant::TENANT_KEY, $tenant->id);

    $this->actingAs($this->admin)
        ->get('/atendimentos')
        ->assertOk()
        ->assertInertia(function ($page) use ($tenant, $conversation) {
            $page->where('tenant.id', $tenant->id)->where('tenant.is_workspace', true);

            expect(conversationIdsIn($page->toArray()['props']['sections']))->toContain($conversation->id);
        });

    $this->actingAs($this->admin)
        ->delete('/admin/workspace')
        ->assertRedirect('/admin/tenants')
        ->assertSessionMissing(IdentifyTenant::TENANT_KEY);
});

it('refuses to enter a suspended tenant', function (): void {
    $tenant = Tenant::factory()->create(['status' => 'suspended', 'suspended_at' => now()]);

    $this->actingAs($this->admin)
        ->post("/admin/tenants/{$tenant->id}/acessar")
        ->assertForbidden();
});

it('reports message and token usage per tenant', function (): void {
    $tenant = tenant();
    $conversation = Conversation::factory()->create();

    $conversation->messages()->createMany([
        ['tenant_id' => $tenant->id, 'direction' => 'inbound', 'body' => 'oi'],
        ['tenant_id' => $tenant->id, 'direction' => 'inbound', 'body' => 'tudo bem?'],
        ['tenant_id' => $tenant->id, 'direction' => 'outbound', 'body' => 'olá'],
    ]);

    AiInteraction::factory()->create([
        'conversation_id' => $conversation->id,
        'input_tokens' => 1200,
        'output_tokens' => 300,
        'cost_micro_cents' => 9 * AiCostCalculator::MICRO_CENTS_PER_CENT,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/uso')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.messages_in', 2)
            ->where('totals.messages_out', 1)
            ->where('totals.input_tokens', 1200)
            ->where('totals.output_tokens', 300)
            ->where('totals.ai_cost_micro_cents', 9 * AiCostCalculator::MICRO_CENTS_PER_CENT)
            ->where('rows.0.name', $tenant->name));
});

it('sends a user straight to the inbox after signing in', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, null, ['password' => 'senha-secreta']);

    $this->post('/entrar', ['email' => $user->email, 'password' => 'senha-secreta'])
        ->assertRedirect('/atendimentos');
});

it('exposes version 7 uuids instead of sequential identifiers', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);
    $role = Role::factory()->create();

    $uuidV7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    expect($tenant->id)->toMatch($uuidV7)
        ->and($user->id)->toMatch($uuidV7)
        ->and($role->id)->toMatch($uuidV7);
});
