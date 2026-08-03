<?php

namespace App\Events\Conversations;

use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationHeldAfterHours
{
    use Dispatchable, SerializesModels;

    public function __construct(public Conversation $conversation) {}
}
