<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantResolver;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public const TENANT_KEY = 'current_tenant_id';

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $tenant = $user instanceof User
            ? $this->resolver->forUser($user, $request->session()->get(self::TENANT_KEY))
            : null;

        if ($tenant === null) {
            $request->session()->forget(self::TENANT_KEY);
        } else {
            $request->session()->put(self::TENANT_KEY, $tenant->id);
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
