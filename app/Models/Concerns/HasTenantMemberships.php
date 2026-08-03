<?php

namespace App\Models\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Scopes\TenantMembershipScope;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasTenantMemberships
{
    public static function bootHasTenantMemberships(): void
    {
        static::addGlobalScope(new TenantMembershipScope);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(TenantMembership::class)
            ->withPivot([
                'id',
                'is_active',
                'hides_other_conversations',
                'signs_messages',
                'work_days',
                'work_starts_at',
                'work_ends_at',
                'blocked_until',
            ])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function membership(Tenant|string|null $tenant = null): ?TenantMembership
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : ($tenant ?? app(TenantContext::class)->id());

        if ($tenantId === null) {
            return null;
        }

        return $this->memberships->firstWhere('tenant_id', $tenantId);
    }

    public function belongsToTenant(Tenant|string $tenant): bool
    {
        return $this->membership($tenant) !== null;
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function accessibleTenants(): Collection
    {
        $tenants = $this->is_super_admin
            ? Tenant::query()->orderBy('name')->get()
            : $this->tenants()->orderBy('name')->get();

        return $tenants->filter(fn (Tenant $tenant): bool => $tenant->isReachableBy($this))->values();
    }

    public function scopeAcrossTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantMembershipScope::class);
    }
}
