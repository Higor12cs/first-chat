<?php

namespace App\Listeners\Conversations;

use App\Events\Conversations\ConversationAssigned;
use App\Events\Conversations\ConversationHeldAfterHours;
use App\Events\Conversations\ConversationQueued;
use App\Events\Conversations\ConversationUpdated;

class AnnounceConversationChange
{
    public function announce(ConversationAssigned|ConversationQueued|ConversationHeldAfterHours $event): void
    {
        ConversationUpdated::dispatch($event->conversation->refresh());
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ConversationAssigned::class => 'announce',
            ConversationQueued::class => 'announce',
            ConversationHeldAfterHours::class => 'announce',
        ];
    }
}
