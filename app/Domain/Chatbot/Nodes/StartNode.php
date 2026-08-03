<?php

namespace App\Domain\Chatbot\Nodes;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Conversations\Enums\ConversationStatus;

class StartNode extends BaseFlowNode
{
    public function type(): string
    {
        return 'start';
    }

    public function label(): string
    {
        return 'Início';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $conversation = $context->conversation;

        if ($conversation->status !== ConversationStatus::Bot) {
            $conversation->forceFill(['status' => ConversationStatus::Bot])->save();
        }

        return FlowStep::next(stateChanges: [
            'no_action_minutes' => $this->minutes($context),
            'no_action' => (string) $context->data('no_action', config('chatbot.no_action')),
            'no_action_service_queue_id' => $context->data('no_action_service_queue_id'),
        ]);
    }

    private function minutes(FlowContext $context): int
    {
        return max(1, (int) $context->data('no_action_minutes', config('chatbot.no_action_minutes')));
    }
}
