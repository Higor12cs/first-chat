<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use App\Models\User;

function createCompany(string $name, ?User $owner = null): Tenant
{
    return app(CreateTenant::class)->handle(
        name: $name,
        ownerName: $owner?->name ?? 'Dona da Conta',
        ownerEmail: $owner?->email ?? Str::slug($name).'@example.com',
        ownerPassword: 'senha-secreta',
        owner: $owner,
    );
}

it('creates the owner account when none was given', function (): void {
    $tenant = createCompany('Consys');

    $owner = User::query()->where('email', 'consys@example.com')->first();

    expect($owner)->not->toBeNull()
        ->and($owner->memberships()->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

it('puts the same account in charge of a second company', function (): void {
    $first = createCompany('Consys');

    $owner = User::query()->where('email', 'consys@example.com')->first();

    $second = createCompany('Freevolt', $owner);

    expect(User::query()->where('email', 'consys@example.com')->count())->toBe(1)
        ->and($owner->memberships()->pluck('tenant_id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id]);
});

it('gives the reused owner the administrator role of each company', function (): void {
    $first = createCompany('Consys');

    $owner = User::query()->where('email', 'consys@example.com')->first();

    $second = createCompany('Freevolt', $owner);

    $roles = Role::query()
        ->acrossTenants()
        ->whereIn('tenant_id', [$first->id, $second->id])
        ->where('slug', 'administrador')
        ->pluck('id');

    expect($owner->roles()->acrossTenants()->pluck('roles.id')->all())
        ->toEqualCanonicalizing($roles->all());
});

it('keeps each company with its own default queue', function (): void {
    $first = createCompany('Consys');

    $owner = User::query()->where('email', 'consys@example.com')->first();

    $second = createCompany('Freevolt', $owner);

    foreach ([$first, $second] as $tenant) {
        $queues = app(TenantContext::class)->run($tenant, fn (): int => ServiceQueue::query()->count());

        expect($queues)->toBe(1);
    }
});
