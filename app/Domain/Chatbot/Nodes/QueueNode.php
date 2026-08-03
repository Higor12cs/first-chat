<?php

namespace App\Domain\Chatbot\Nodes;

use App\Actions\Cards\SendCard;
use App\Actions\Conversations\TransferConversation;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\Card;
use App\Models\ServiceQueue;
use App\Models\User;

class QueueNode extends BaseFlowNode
{
    public function __construct(
        private readonly TransferConversation $transferConversation,
        private readonly SendCard $sendCard,
    ) {}

    public function type(): string
    {
        return 'queue';
    }

    public function label(): string
    {
        return 'Transferir';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $queue = ServiceQueue::query()->find($context->data('service_queue_id'));

        if ($queue === null) {
            return FlowStep::next();
        }

        $cardId = $context->data('card_id');

        if (filled($cardId)) {
            $this->sendCard->handle(
                $context->conversation,
                Card::query()->active()->find($cardId),
                MessageSource::Bot,
            );
        }

        $assignee = $this->assignee($context, $queue);

        $assignee === null
            ? $this->transferConversation->toWaiting($context->conversation, $queue, applyAutomations: true)
            : $this->transferConversation->toManual($context->conversation, $queue, $assignee);

        return FlowStep::stop();
    }

    private function assignee(FlowContext $context, ServiceQueue $queue): ?User
    {
        $userId = $context->data('user_id');

        if (blank($userId)) {
            return null;
        }

        return $queue->users()->active()->whereKey($userId)->first();
    }
}
