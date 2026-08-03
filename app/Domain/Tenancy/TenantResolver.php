<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use App\Models\User;

class TenantResolver
{
    public function forUser(User $user, ?string $tenantId): ?Tenant
    {
        if ($tenantId !== null) {
            $tenant = Tenant::query()->find($tenantId);

            if ($tenant !== null && $this->allows($user, $tenant)) {
                return $tenant;
            }
        }

        if ($user->is_super_admin) {
            return null;
        }

        $tenants = $user->accessibleTenants();

        return $tenants->count() === 1 ? $tenants->first() : null;
    }

    public function allows(User $user, Tenant $tenant): bool
    {
        if (! $tenant->isReachableBy($user)) {
            return false;
        }

        return $user->is_super_admin || $user->belongsToTenant($tenant);
    }
}
