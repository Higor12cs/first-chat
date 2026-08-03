<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Jobs\Messaging\SendTypingIndicator;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationPresenceController extends Controller
{
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['typing' => ['required', 'boolean']]);

        if ($conversation->isOpen()) {
            SendTypingIndicator::dispatch($conversation, $request->boolean('typing'));
        }

        return response()->json(['sent' => true]);
    }
}
