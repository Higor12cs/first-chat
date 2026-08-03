<?php

namespace App\Jobs\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use Closure;

trait InteractsWithTenant
{
    protected function forTenant(Tenant $tenant, Closure $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }
}
