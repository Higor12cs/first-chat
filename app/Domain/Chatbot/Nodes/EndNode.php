<?php

namespace App\Domain\Chatbot\Nodes;

use App\Actions\Conversations\CloseConversation;
use App\Actions\Messaging\SendMessage;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Chatbot\Support\FlowVariables;
use App\Domain\Conversations\Enums\MessageSource;

class EndNode extends BaseFlowNode
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly CloseConversation $closeConversation,
        private readonly FlowVariables $variables,
    ) {}

    public function type(): string
    {
        return 'end';
    }

    public function label(): string
    {
        return 'Fim';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $text = (string) $context->data('text', '');

        if (filled($text)) {
            $this->sendMessage->handle(
                conversation: $context->conversation,
                body: $this->variables->replace($text, $context),
                source: MessageSource::Bot,
            );
        }

        if ($context->data('close_conversation', false)) {
            $this->closeConversation->handle($context->conversation, null, 'Encerrado pelo fluxo.');
        }

        return FlowStep::stop();
    }
}
