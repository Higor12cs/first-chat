<?php

namespace App\Actions\Conversations;

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\ConversationClosed;
use App\Events\Conversations\ConversationUpdated;
use App\Models\Conversation;
use App\Models\User;

class CloseConversation
{
    public function handle(Conversation $conversation, ?User $user = null, ?string $reason = null): Conversation
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => $user?->id,
            'metadata' => [...$conversation->metadata ?? [], 'close_reason' => $reason],
        ])->save();

        ConversationClosed::dispatch($conversation, $user);
        ConversationUpdated::dispatch($conversation);

        return $conversation;
    }
}
