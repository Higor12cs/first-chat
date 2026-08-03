<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScreenIsUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->locked_at === null || $request->routeIs('lock.*', 'logout')) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Tela bloqueada.'], 423)
            : redirect()->route('lock.show');
    }
}
