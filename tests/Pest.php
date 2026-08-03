<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * @param  array<int, array<string, mixed>>  $sections
 * @return array<int, string>
 */
function conversationIdsIn(array $sections): array
{
    return collect($sections)
        ->flatMap(fn (array $section): array => array_column($section['conversations'], 'id'))
        ->all();
}

function tenant(array $attributes = []): Tenant
{
    $tenant = Tenant::factory()->create($attributes);

    app(TenantContext::class)->set($tenant);

    return $tenant;
}

/**
 * @param  array<int, string>|null  $permissions
 * @param  array<string, mixed>  $attributes
 */
function userFor(Tenant $tenant, ?array $permissions = null, array $attributes = []): User
{
    $membership = collect($attributes)->only(membershipFields())->all();
    $account = collect($attributes)->except(membershipFields())->all();

    $user = app(TenantContext::class)->run($tenant, fn (): User => User::factory()->create($account));

    membershipFor($user, $tenant, $membership);

    $role = Role::factory()->create(['tenant_id' => $tenant->id]);
    $role->syncPermissions($permissions ?? PermissionRegistry::keys());

    $user->roles()->sync([$role->id]);

    return app(TenantContext::class)->run($tenant, fn (): User => $user->fresh());
}

/**
 * @param  array<string, mixed>  $attributes
 */
function membershipFor(User $user, Tenant $tenant, array $attributes = []): void
{
    $user->memberships()->updateOrCreate(['tenant_id' => $tenant->id], $attributes);

    $user->unsetRelation('memberships');
}

/**
 * @return array<int, string>
 */
function membershipFields(): array
{
    return [
        'is_active',
        'hides_other_conversations',
        'signs_messages',
        'work_days',
        'work_starts_at',
        'work_ends_at',
        'blocked_until',
    ];
}
