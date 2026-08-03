<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\MarkConversationAsRead;
use App\Actions\Conversations\ReopenConversation;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationStatusController extends Controller
{
    public function close(Request $request, Conversation $conversation, CloseConversation $closeConversation): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $closeConversation->handle($conversation, $request->user(), $request->string('reason')->value() ?: null);

        return back()->with('success', 'Atendimento encerrado.');
    }

    public function reopen(Request $request, Conversation $conversation, ReopenConversation $reopenConversation): RedirectResponse
    {
        $reopenConversation->handle($conversation, $request->user());

        return back()->with('success', 'Atendimento reaberto.');
    }

    public function read(Conversation $conversation, MarkConversationAsRead $markAsRead): RedirectResponse
    {
        $markAsRead->handle($conversation);

        return back();
    }
}
