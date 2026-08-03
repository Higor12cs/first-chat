<?php

namespace App\Events\Conversations;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public ConversationSection $section,
        public ?ServiceQueue $serviceQueue = null,
        public ?User $assignee = null,
        public ?ChatFlow $chatFlow = null,
        public ?User $transferredBy = null,
    ) {}
}
