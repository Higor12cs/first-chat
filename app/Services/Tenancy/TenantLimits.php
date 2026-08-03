<?php

namespace App\Services\Tenancy;

use App\Domain\Tenancy\TenantContext;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class TenantLimits
{
    public function __construct(private readonly TenantContext $context) {}

    public function guardUserCreation(): void
    {
        $this->guard(
            'max_users',
            fn (Tenant $tenant): int => $tenant->users()->count(),
            'A conta permite no máximo :limit usuários.',
        );
    }

    public function guardConnectionCreation(): void
    {
        $this->guard(
            'max_connections',
            fn (Tenant $tenant): int => ChannelConnection::query()->where('tenant_id', $tenant->id)->count(),
            'A conta permite no máximo :limit conexões.',
        );
    }

    /**
     * @return array<string, array{used: int, limit: int|null}>
     */
    public function usage(Tenant $tenant): array
    {
        return [
            'users' => [
                'used' => $tenant->users()->count(),
                'limit' => $tenant->limit('max_users'),
            ],
            'connections' => [
                'used' => ChannelConnection::query()->where('tenant_id', $tenant->id)->count(),
                'limit' => $tenant->limit('max_connections'),
            ],
        ];
    }

    /**
     * @param  callable(Tenant): int  $counter
     */
    private function guard(string $key, callable $counter, string $message): void
    {
        $tenant = $this->context->get();

        if ($tenant === null) {
            return;
        }

        $limit = $tenant->limit($key);

        if ($limit === null || $counter($tenant) < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'name' => str_replace(':limit', (string) $limit, $message),
        ]);
    }
}
