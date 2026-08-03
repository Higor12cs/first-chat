<?php

namespace App\Actions\Conversations;

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\ConversationUpdated;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;

class ReopenConversation
{
    public function __construct(private readonly TakeConversation $takeConversation) {}

    public function handle(Conversation $conversation, ?User $user = null): Conversation
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Pending,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ])->save();

        if ($user === null || $conversation->is_group || ServiceQueue::default() === null) {
            ConversationUpdated::dispatch($conversation);

            return $conversation;
        }

        return $this->takeConversation->handle($conversation, $user);
    }
}
