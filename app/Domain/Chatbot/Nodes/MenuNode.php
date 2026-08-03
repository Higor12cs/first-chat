<?php

namespace App\Domain\Chatbot\Nodes;

use App\Actions\Messaging\SendMessage;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Chatbot\Support\FlowVariables;
use App\Domain\Conversations\Enums\MessageSource;

class MenuNode extends BaseFlowNode
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly FlowVariables $variables,
    ) {}

    public function type(): string
    {
        return 'menu';
    }

    public function label(): string
    {
        return 'Menu';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $options = $this->options($context);

        $body = collect($options)
            ->map(fn (array $option, int $index): string => sprintf('%d) %s', $index + 1, $option['label']))
            ->prepend($this->variables->replace((string) $context->data('text', ''), $context))
            ->filter()
            ->implode("\n");

        $this->sendMessage->handle(
            conversation: $context->conversation,
            body: $body,
            source: MessageSource::Bot,
        );

        return FlowStep::wait($this->type());
    }

    public function resume(FlowContext $context): FlowStep
    {
        $options = $this->options($context);
        $answer = mb_strtolower($context->answer());

        foreach ($options as $index => $option) {
            $matchesNumber = $answer === (string) ($index + 1);
            $matchesLabel = $answer === mb_strtolower((string) $option['label']);

            if ($matchesNumber || $matchesLabel) {
                return FlowStep::next($option['id'] ?? (string) $index);
            }
        }

        $retry = (string) $context->data('invalid_message', 'Opção inválida. Escolha um número da lista.');

        $this->sendMessage->handle(
            conversation: $context->conversation,
            body: $retry,
            source: MessageSource::Bot,
        );

        return FlowStep::wait($this->type());
    }

    /**
     * @return array<int, array{id?: string, label: string}>
     */
    private function options(FlowContext $context): array
    {
        return array_values((array) $context->data('options', []));
    }
}
