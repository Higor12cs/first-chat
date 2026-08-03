<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Conversations\AddConversationNote;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationNoteController extends Controller
{
    public function store(Request $request, Conversation $conversation, AddConversationNote $addNote): RedirectResponse
    {
        $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $addNote->handle($conversation, $request->user(), $request->string('body')->value());

        return back()->with('success', 'Nota registrada.');
    }

    public function destroy(Conversation $conversation, ConversationNote $note): RedirectResponse
    {
        abort_unless($note->conversation_id === $conversation->id, 404);

        $note->delete();

        return back()->with('success', 'Nota removida.');
    }
}
