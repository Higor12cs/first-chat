<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\User;

class AddConversationNote
{
    public function handle(Conversation $conversation, User $user, string $body): ConversationNote
    {
        return ConversationNote::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $body,
        ]);
    }
}
