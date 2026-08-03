<?php

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantResolver;
use App\Http\Middleware\IdentifyTenant;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$tenantInScope = fn (User $user): ?Tenant => app(TenantResolver::class)
    ->forUser($user, session(IdentifyTenant::TENANT_KEY));

$listensToTenant = fn (User $user, string $tenantId): bool => $tenantInScope($user)?->id === $tenantId;

Broadcast::channel('tenants.{tenantId}.conversations', $listensToTenant);

Broadcast::channel('tenants.{tenantId}.connections', $listensToTenant);

Broadcast::channel('conversations.{conversationId}', function (User $user, string $conversationId) use ($tenantInScope): bool {
    $tenant = $tenantInScope($user);

    if ($tenant === null) {
        return false;
    }

    return app(TenantContext::class)->run($tenant, fn (): bool => Conversation::query()
        ->visibleTo($user)
        ->whereKey($conversationId)
        ->exists());
});
