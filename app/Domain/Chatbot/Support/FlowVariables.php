<?php

namespace App\Domain\Chatbot\Support;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Support\Messaging\MessageVariables;

class FlowVariables
{
    public function __construct(private readonly MessageVariables $variables) {}

    public function replace(string $text, FlowContext $context): string
    {
        return $this->variables->apply(
            $text,
            $context->conversation,
            collect($context->answers())
                ->mapWithKeys(fn (mixed $value, string $key): array => ["resposta.{$key}" => $value])
                ->all(),
        );
    }
}
