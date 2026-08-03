<?php

namespace App\Domain\Chatbot\Contracts;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;

interface FlowNode
{
    public function type(): string;

    public function label(): string;

    public function execute(FlowContext $context): FlowStep;

    public function resume(FlowContext $context): FlowStep;
}
