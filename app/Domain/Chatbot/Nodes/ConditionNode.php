<?php

namespace App\Domain\Chatbot\Nodes;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;

class ConditionNode extends BaseFlowNode
{
    public function type(): string
    {
        return 'condition';
    }

    public function label(): string
    {
        return 'Condição';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $value = $this->valueOf($context);
        $expected = (string) $context->data('value', '');

        $result = match ((string) $context->data('operator', 'equals')) {
            'contains' => str_contains(mb_strtolower($value), mb_strtolower($expected)),
            'not_equals' => mb_strtolower($value) !== mb_strtolower($expected),
            'filled' => filled($value),
            'empty' => blank($value),
            default => mb_strtolower($value) === mb_strtolower($expected),
        };

        return FlowStep::next($result ? 'true' : 'false');
    }

    private function valueOf(FlowContext $context): string
    {
        $field = (string) $context->data('field', '');
        $contact = $context->conversation->contact;

        return (string) match ($field) {
            'contato.nome' => $contact->name,
            'contato.telefone' => $contact->phone,
            'contato.email' => $contact->email,
            'atendimento.canal' => $context->conversation->channel->value,
            'mensagem' => $context->answer(),
            default => data_get($context->answers(), $field, ''),
        };
    }
}
