<?php

namespace App\Domain\Chatbot\Nodes;

use App\Actions\Cards\SendCard;
use App\Actions\Messaging\SendMessage;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Chatbot\Support\FlowVariables;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\Card;

class MessageNode extends BaseFlowNode
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly SendCard $sendCard,
        private readonly FlowVariables $variables,
    ) {}

    public function type(): string
    {
        return 'message';
    }

    public function label(): string
    {
        return 'Mensagem';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $cardId = $context->data('card_id');

        if (filled($cardId)) {
            $this->sendCard->handle(
                $context->conversation,
                Card::query()->active()->find($cardId),
                MessageSource::Bot,
            );

            return FlowStep::next();
        }

        $text = (string) $context->data('text', '');

        if (filled($text)) {
            $this->sendMessage->handle(
                conversation: $context->conversation,
                body: $this->variables->replace($text, $context),
                source: MessageSource::Bot,
            );
        }

        return FlowStep::next();
    }
}
