<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConversationIsVisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = $request->route('conversation');
        $user = $request->user();

        if ($conversation instanceof Conversation && $user !== null) {
            abort_unless(
                Conversation::query()->visibleTo($user)->whereKey($conversation->getKey())->exists(),
                404,
            );
        }

        return $next($request);
    }
}
