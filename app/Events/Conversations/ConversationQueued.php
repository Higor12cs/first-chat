<?php

namespace App\Events\Conversations;

use App\Models\Conversation;
use App\Models\ServiceQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationQueued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public ServiceQueue $serviceQueue,
    ) {}
}
