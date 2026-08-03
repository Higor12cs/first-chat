<?php

namespace App\Services\Chatbot;

use App\Domain\Chatbot\DataObjects\FlowContext;
use App\Domain\Chatbot\DataObjects\FlowStep;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\Message;

class FlowEngine
{
    private const MAX_STEPS_PER_RUN = 25;

    public function __construct(private readonly FlowNodeRegistry $nodes) {}

    public function advance(Conversation $conversation, ?Message $incoming = null): void
    {
        $flow = $conversation->chatFlow;

        if ($flow === null || ! $flow->is_active) {
            return;
        }

        $state = $conversation->flow_state ?? [];

        if (data_get($state, 'finished', false)) {
            return;
        }

        $nodeId = data_get($state, 'node_id') ?? data_get($flow->startNode(), 'id');

        if ($nodeId === null) {
            return;
        }

        $awaiting = data_get($state, 'awaiting');

        for ($step = 0; $step < self::MAX_STEPS_PER_RUN; $step++) {
            $node = $flow->node((string) $nodeId);
            $handler = $this->nodes->find(data_get($node, 'type'));

            if ($node === null || $handler === null) {
                $this->finish($conversation, $state, (string) $nodeId);

                return;
            }

            $context = new FlowContext($conversation, $node, $state, $incoming);

            $result = $awaiting !== null
                ? $handler->resume($context)
                : $handler->execute($context);

            $awaiting = null;
            $incoming = null;
            $state = [...$state, ...$result->stateChanges];

            if ($result->action === FlowStep::ACTION_WAIT) {
                $this->persist($conversation, [...$state, 'node_id' => $nodeId, 'awaiting' => $result->awaiting]);
                $this->scheduleNoAction($conversation, $state);

                return;
            }

            if ($result->action === FlowStep::ACTION_STOP) {
                $this->finish($conversation, $state, (string) $nodeId);

                return;
            }

            $nextId = $this->nextNodeId($flow, (string) $nodeId, $result->handle);

            if ($nextId === null) {
                $this->finish($conversation, $state, (string) $nodeId);

                return;
            }

            $nodeId = $nextId;
            $this->persist($conversation, [...$state, 'node_id' => $nodeId, 'awaiting' => null]);
        }
    }

    public function restart(Conversation $conversation, ChatFlow $flow, ?string $nodeId = null): void
    {
        $this->startAt($conversation, $flow, $nodeId);

        $this->advance($conversation->refresh());
    }

    public function startAt(Conversation $conversation, ChatFlow $flow, ?string $nodeId = null): void
    {
        $conversation->forceFill([
            'chat_flow_id' => $flow->id,
            'status' => ConversationStatus::Bot,
            'flow_state' => [
                'node_id' => $nodeId ?? data_get($flow->startNode(), 'id'),
                'answers' => [],
                'awaiting' => null,
                'finished' => false,
            ],
        ])->save();
    }

    public function isAwaitingInput(Conversation $conversation): bool
    {
        return filled(data_get($conversation->flow_state, 'awaiting'))
            && ! data_get($conversation->flow_state, 'finished', false);
    }

    private function nextNodeId(ChatFlow $flow, string $nodeId, ?string $handle): ?string
    {
        $edges = collect($flow->edgesFrom($nodeId));

        $edge = $handle !== null
            ? $edges->firstWhere('sourceHandle', $handle) ?? $edges->first()
            : $edges->first();

        return data_get($edge, 'target');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function scheduleNoAction(Conversation $conversation, array $state): void
    {
        $minutes = (int) data_get($state, 'no_action_minutes', config('chatbot.no_action_minutes'));

        $conversation->forceFill([
            'no_action_at' => now()->addMinutes(max(1, $minutes)),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function finish(Conversation $conversation, array $state, string $nodeId): void
    {
        $this->persist($conversation, [...$state, 'node_id' => $nodeId, 'awaiting' => null, 'finished' => true]);

        $conversation->forceFill(['no_action_at' => null])->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persist(Conversation $conversation, array $state): void
    {
        $conversation->forceFill([
            'flow_state' => [...$conversation->flow_state ?? [], ...$state],
        ])->save();
    }
}
