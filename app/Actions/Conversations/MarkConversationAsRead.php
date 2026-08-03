<?php

namespace App\Actions\Conversations;

use App\Events\Conversations\ConversationUpdated;
use App\Jobs\Messaging\SendReadReceipt;
use App\Models\Conversation;

class MarkConversationAsRead
{
    public function handle(Conversation $conversation): Conversation
    {
        SendReadReceipt::dispatch($conversation);

        if ($conversation->unread_count === 0) {
            return $conversation;
        }

        $conversation->forceFill(['unread_count' => 0])->save();

        ConversationUpdated::dispatch($conversation);

        return $conversation;
    }
}
