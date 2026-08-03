<?php

namespace App\Listeners\Conversations;

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\MessageReceived;
use App\Jobs\Ai\RunAiTurn;
use App\Jobs\Chatbot\AdvanceChatFlow;
use App\Models\Conversation;
use App\Services\Chatbot\FlowEngine;

class RouteInboundMessage
{
    public function __construct(private readonly FlowEngine $engine) {}

    public function handle(MessageReceived $event): void
    {
        $conversation = $event->conversation;

        if ($conversation->is_group || $conversation->assigned_user_id !== null) {
            return;
        }

        if ($this->shouldRunFlow($conversation)) {
            AdvanceChatFlow::dispatch($conversation, $event->message);

            return;
        }

        if ($conversation->ai_objective_id !== null && $conversation->status === ConversationStatus::Ai) {
            RunAiTurn::dispatch($conversation);
        }
    }

    private function shouldRunFlow(Conversation $conversation): bool
    {
        if ($conversation->chat_flow_id === null) {
            return false;
        }

        if (data_get($conversation->flow_state, 'finished', false)) {
            return false;
        }

        return in_array($conversation->status, [ConversationStatus::Pending, ConversationStatus::Bot], true);
    }
}
