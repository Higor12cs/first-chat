<?php

namespace App\Domain\Chatbot\Nodes;

use App\Domain\Chatbot\Contracts\FlowNode;
use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;

abstract class BaseFlowNode implements FlowNode
{
    public function resume(FlowContext $context): FlowStep
    {
        return FlowStep::next();
    }
}
