<?php

namespace App\Domain\Chatbot\Nodes;

use App\Actions\Messaging\SendMessage;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Chatbot\Support\FlowVariables;
use App\Domain\Conversations\Enums\MessageSource;

class QuestionNode extends BaseFlowNode
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly FlowVariables $variables,
    ) {}

    public function type(): string
    {
        return 'question';
    }

    public function label(): string
    {
        return 'Pergunta';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $this->sendMessage->handle(
            conversation: $context->conversation,
            body: $this->variables->replace((string) $context->data('text', ''), $context),
            source: MessageSource::Bot,
        );

        return FlowStep::wait($this->type());
    }

    public function resume(FlowContext $context): FlowStep
    {
        $key = (string) $context->data('save_as', 'resposta');

        return FlowStep::next(stateChanges: [
            'answers' => [...$context->answers(), $key => $context->answer()],
        ]);
    }
}
