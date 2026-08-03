<?php

namespace App\Domain\Tenancy;

use App\Models\Tenant;
use Closure;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;

        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }

    public function runWithoutTenant(Closure $callback): mixed
    {
        $previous = $this->tenant;

        $this->tenant = null;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
