<?php

namespace App\Domain\Chatbot\Nodes;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\ConversationUpdated;
use App\Jobs\Ai\RunAiTurn;
use App\Models\AiObjective;

class AiNode extends BaseFlowNode
{
    public function type(): string
    {
        return 'ai';
    }

    public function label(): string
    {
        return 'Inteligência Artificial';
    }

    public function execute(FlowContext $context): FlowStep
    {
        $objective = AiObjective::query()->find($context->data('ai_objective_id'));

        if ($objective === null) {
            return FlowStep::next();
        }

        $context->conversation->forceFill([
            'ai_objective_id' => $objective->id,
            'status' => ConversationStatus::Ai,
        ])->save();

        ConversationUpdated::dispatch($context->conversation);

        RunAiTurn::dispatch($context->conversation);

        return FlowStep::stop();
    }
}
