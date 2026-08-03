<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $permission = PermissionRegistry::forRoute($request->route()?->getName());

        if ($permission !== null) {
            abort_unless($request->user()?->hasPermission($permission->key), 403);
        }

        return $next($request);
    }
}
