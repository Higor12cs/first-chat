<?php

namespace App\Services\Chatbot;

use App\Domain\Chatbot\Contracts\FlowNode;
use App\Domain\Chatbot\Nodes\AiNode;
use App\Domain\Chatbot\Nodes\CloseNode;
use App\Domain\Chatbot\Nodes\ConditionNode;
use App\Domain\Chatbot\Nodes\EndNode;
use App\Domain\Chatbot\Nodes\MenuNode;
use App\Domain\Chatbot\Nodes\MessageNode;
use App\Domain\Chatbot\Nodes\QuestionNode;
use App\Domain\Chatbot\Nodes\QueueNode;
use App\Domain\Chatbot\Nodes\StartNode;
use Illuminate\Contracts\Container\Container;

class FlowNodeRegistry
{
    /**
     * @var array<string, class-string<FlowNode>>
     */
    private const NODES = [
        'start' => StartNode::class,
        'message' => MessageNode::class,
        'menu' => MenuNode::class,
        'question' => QuestionNode::class,
        'condition' => ConditionNode::class,
        'ai' => AiNode::class,
        'queue' => QueueNode::class,
        'close' => CloseNode::class,
        'end' => EndNode::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function find(?string $type): ?FlowNode
    {
        return isset(self::NODES[$type]) ? $this->container->make(self::NODES[$type]) : null;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        return collect(self::NODES)
            ->map(function (string $class, string $type): array {
                $node = $this->container->make($class);

                return ['value' => $type, 'label' => $node->label()];
            })
            ->values()
            ->all();
    }
}
