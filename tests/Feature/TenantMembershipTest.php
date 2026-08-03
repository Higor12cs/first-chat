<?php

use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Contact;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;

it('sends the platform administrator straight to the admin area', function (): void {
    $admin = User::factory()->superAdmin()->create(['password' => 'senha-secreta']);

    $this->post('/entrar', ['email' => $admin->email, 'password' => 'senha-secreta'])
        ->assertRedirect('/admin/tenants');
});

it('signs a single tenant user directly into the tenant', function (): void {
    $tenant = tenant();
    $user = userFor($tenant, null, ['password' => 'senha-secreta']);

    $this->post('/entrar', ['email' => $user->email, 'password' => 'senha-secreta'])
        ->assertRedirect('/atendimentos')
        ->assertSessionHas(IdentifyTenant::TENANT_KEY, $tenant->id);
});

it('asks a user of many tenants to pick one', function (): void {
    $first = tenant();
    $user = userFor($first, null, ['password' => 'senha-secreta']);

    $second = Tenant::factory()->create();
    membershipFor($user, $second);

    $this->post('/entrar', ['email' => $user->email, 'password' => 'senha-secreta'])
        ->assertRedirect('/selecionar-conta')
        ->assertSessionMissing(IdentifyTenant::TENANT_KEY);

    $this->actingAs($user)
        ->get('/selecionar-conta')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/SelectTenant')->has('tenants', 2));
});

it('opens the inbox of the chosen tenant', function (): void {
    $first = tenant();
    $user = userFor($first);
    Contact::factory()->create(['name' => 'Contato Alfa']);

    $second = tenant();
    membershipFor($user, $second);
    Contact::factory()->create(['name' => 'Contato Beta']);

    $role = Role::factory()->create(['tenant_id' => $second->id]);
    $role->syncPermissions(PermissionRegistry::keys());
    $user->roles()->attach($role);

    $this->actingAs($user)
        ->post('/selecionar-conta', ['tenant_id' => $second->id])
        ->assertRedirect('/atendimentos')
        ->assertSessionHas(IdentifyTenant::TENANT_KEY, $second->id);

    $this->actingAs($user)
        ->get('/contatos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('contacts.data.0.name', 'Contato Beta')->has('contacts.data', 1));
});

it('refuses a tenant the user does not belong to', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);
    $foreign = Tenant::factory()->create();

    $this->actingAs($user)
        ->post('/selecionar-conta', ['tenant_id' => $foreign->id])
        ->assertSessionHasErrors('tenant_id');
});

it('sends an administrator without a tenant to the selection screen', function (): void {
    $admin = User::factory()->superAdmin()->create();
    Tenant::factory()->create();

    app(TenantContext::class)->forget();

    $this->actingAs($admin)->get('/atendimentos')->assertRedirect('/selecionar-conta');
    $this->actingAs($admin)->get('/painel')->assertRedirect('/selecionar-conta');
});

it('keeps the schedule of each tenant apart', function (): void {
    $first = tenant();
    $user = userFor($first, null, ['work_days' => [1], 'work_starts_at' => '08:00', 'work_ends_at' => '12:00']);

    $second = Tenant::factory()->create();
    membershipFor($user, $second, ['work_days' => [5], 'work_starts_at' => '13:00', 'work_ends_at' => '18:00']);

    app(TenantContext::class)->run($first, function () use ($user): void {
        expect($user->work_days)->toBe([1])->and($user->work_starts_at)->toBe('08:00:00');
    });

    app(TenantContext::class)->run($second, function () use ($user): void {
        expect($user->work_days)->toBe([5])->and($user->work_starts_at)->toBe('13:00:00');
    });
});

it('locks a user out of a tenant it is inactive in', function (): void {
    $first = tenant();
    $user = userFor($first, null, ['is_active' => false]);

    $second = Tenant::factory()->create();
    membershipFor($user, $second);

    $this->actingAs($user)
        ->withSession([IdentifyTenant::TENANT_KEY => $first->id])
        ->get('/painel')
        ->assertRedirect('/entrar');

    $this->actingAs($user)
        ->post('/selecionar-conta', ['tenant_id' => $second->id])
        ->assertRedirect('/atendimentos');

    $this->actingAs($user)
        ->withSession([IdentifyTenant::TENANT_KEY => $second->id])
        ->get('/painel')
        ->assertOk();
});

it('blocks a tenant once the access date has passed', function (): void {
    $tenant = tenant(['access_expires_at' => now()->subDay()]);
    $user = userFor($tenant);

    $this->actingAs($user)->get('/atendimentos')->assertRedirect('/selecionar-conta');

    $this->actingAs($user)
        ->get('/selecionar-conta')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tenants', 0));
});

it('keeps a tenant open until the end of the access date', function (): void {
    $tenant = tenant(['access_expires_at' => now()]);
    $user = userFor($tenant);

    $this->actingAs($user)->get('/atendimentos')->assertOk();
});

it('updates the access date of many tenants at once', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();
    $untouched = Tenant::factory()->create(['access_expires_at' => '2027-01-31']);

    $this->actingAs($admin)
        ->put('/admin/tenants/acesso', [
            'tenants' => [$first->id, $second->id],
            'access_expires_at' => '2026-12-31',
        ])
        ->assertRedirect();

    expect($first->fresh()->access_expires_at->toDateString())->toBe('2026-12-31')
        ->and($second->fresh()->access_expires_at->toDateString())->toBe('2026-12-31')
        ->and($untouched->fresh()->access_expires_at->toDateString())->toBe('2027-01-31');
});

it('clears the access date of the selected tenants', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create(['access_expires_at' => '2026-12-31']);

    $this->actingAs($admin)
        ->put('/admin/tenants/acesso', ['tenants' => [$tenant->id], 'access_expires_at' => null])
        ->assertRedirect();

    expect($tenant->fresh()->access_expires_at)->toBeNull();
});

it('keeps the bulk access update away from a tenant user', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)
        ->put('/admin/tenants/acesso', ['tenants' => [$tenant->id], 'access_expires_at' => '2026-12-31'])
        ->assertForbidden();
});
