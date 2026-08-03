<?php

namespace App\Services\Conversations;

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Events\Conversations\ConversationAssigned;
use App\Events\Conversations\ConversationHeldAfterHours;
use App\Events\Conversations\ConversationQueued;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;
use App\Services\Tenancy\BusinessHours;
use Illuminate\Support\Collection;

class ConversationRouter
{
    public function __construct(private readonly BusinessHours $hours) {}

    public function resolveOpenConversation(
        ChannelConnection $connection,
        ContactChannel $contactChannel,
        bool $inbound = true,
    ): Conversation {
        $conversation = Conversation::query()
            ->where('contact_channel_id', $contactChannel->id)
            ->active()
            ->latest('last_message_at')
            ->first();

        if ($conversation !== null) {
            return $this->applyBusinessHours($conversation, $connection, $inbound);
        }

        $outsideHours = $inbound
            && ! $contactChannel->is_group
            && $this->hours->holdsOutsideHours($connection->tenant);

        $flow = $contactChannel->is_group || $outsideHours ? null : $this->flowFor($connection);

        $conversation = Conversation::create([
            'tenant_id' => $connection->tenant_id,
            'contact_id' => $contactChannel->contact_id,
            'contact_channel_id' => $contactChannel->id,
            'channel_connection_id' => $connection->id,
            'channel' => $connection->channel,
            'status' => match (true) {
                $outsideHours => ConversationStatus::AfterHours,
                $flow !== null => ConversationStatus::Bot,
                default => ConversationStatus::Pending,
            },
            'is_group' => $contactChannel->is_group,
            'chat_flow_id' => $flow?->id,
        ]);

        if ($outsideHours) {
            ConversationHeldAfterHours::dispatch($conversation);

            return $conversation;
        }

        if ($flow !== null || $contactChannel->is_group) {
            return $conversation;
        }

        $queue = $this->defaultQueueFor($connection);

        if ($queue !== null) {
            $this->moveToQueue($conversation, $queue);
        }

        return $conversation;
    }

    private function applyBusinessHours(
        Conversation $conversation,
        ChannelConnection $connection,
        bool $inbound,
    ): Conversation {
        $outsideHours = ! $conversation->is_group && $this->hours->holdsOutsideHours($connection->tenant);

        if ($conversation->status === ConversationStatus::AfterHours) {
            return $outsideHours && $inbound ? $conversation : $this->resume($conversation, $connection);
        }

        return $inbound && $outsideHours && $conversation->assigned_user_id === null
            ? $this->hold($conversation)
            : $conversation;
    }

    private function hold(Conversation $conversation): Conversation
    {
        $conversation->forceFill([
            'status' => ConversationStatus::AfterHours,
            'no_action_at' => null,
        ])->save();

        ConversationHeldAfterHours::dispatch($conversation);

        return $conversation;
    }

    private function resume(Conversation $conversation, ChannelConnection $connection): Conversation
    {
        $flow = $this->flowFor($connection);

        $conversation->forceFill([
            'status' => $flow === null ? ConversationStatus::Pending : ConversationStatus::Bot,
            'chat_flow_id' => $flow?->id,
        ])->save();

        $queue = $flow === null ? $conversation->serviceQueue ?? $this->defaultQueueFor($connection) : null;

        if ($queue !== null) {
            $this->moveToQueue($conversation, $queue);
        }

        return $conversation;
    }

    public function moveToQueue(
        Conversation $conversation,
        ServiceQueue $queue,
        bool $applyAutomations = true,
        bool $announce = true,
    ): Conversation {
        $conversation->forceFill([
            'service_queue_id' => $queue->id,
            'ai_objective_id' => $applyAutomations
                ? $queue->ai_objective_id ?? $conversation->ai_objective_id
                : $conversation->ai_objective_id,
            'status' => $applyAutomations && $queue->ai_objective_id !== null
                ? ConversationStatus::Ai
                : $conversation->status,
        ])->save();

        if ($announce) {
            ConversationQueued::dispatch($conversation, $queue);
        }

        if ($applyAutomations && $conversation->assigned_user_id === null) {
            $this->autoAssign($conversation, $queue);
        }

        return $conversation;
    }

    public function assign(
        Conversation $conversation,
        ?User $user,
        ?User $assignedBy = null,
        bool $announce = true,
    ): Conversation {
        if ($conversation->is_group) {
            return $conversation;
        }

        $conversation->forceFill([
            'assigned_user_id' => $user?->id,
            'status' => $user !== null ? ConversationStatus::Open : $conversation->status,
            'no_action_at' => $user !== null ? null : $conversation->no_action_at,
        ])->save();

        if ($announce) {
            ConversationAssigned::dispatch($conversation, $user, $assignedBy);
        }

        return $conversation;
    }

    public function release(Conversation $conversation): Conversation
    {
        $conversation->forceFill([
            'assigned_user_id' => null,
            'ai_objective_id' => null,
            'status' => ConversationStatus::Pending,
            'no_action_at' => null,
            'flow_state' => [...$conversation->flow_state ?? [], 'awaiting' => null, 'finished' => true],
        ])->save();

        return $conversation;
    }

    private function flowFor(ChannelConnection $connection): ?ChatFlow
    {
        if ($connection->chat_flow_id === null) {
            return null;
        }

        $flow = ChatFlow::query()->active()->find($connection->chat_flow_id);

        return $flow?->startNode() === null ? null : $flow;
    }

    private function autoAssign(Conversation $conversation, ServiceQueue $queue): void
    {
        $candidate = match ($queue->assignment_strategy) {
            'round_robin' => $this->roundRobinCandidate($queue),
            'least_busy' => $this->leastBusyCandidate($queue),
            default => null,
        };

        if ($candidate !== null) {
            $this->assign($conversation, $candidate);
        }
    }

    private function roundRobinCandidate(ServiceQueue $queue): ?User
    {
        return $this->availableAgents($queue)
            ->sortBy(fn (User $user): string => (string) $user->conversations()
                ->where('service_queue_id', $queue->id)
                ->max('created_at'))
            ->first();
    }

    private function leastBusyCandidate(ServiceQueue $queue): ?User
    {
        return $this->availableAgents($queue)
            ->sortBy(fn (User $user): int => $user->conversations()->active()->count())
            ->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function availableAgents(ServiceQueue $queue): Collection
    {
        return $queue->users()->active()->get()
            ->filter(fn (User $user): bool => $user->canAccessPlatform())
            ->values();
    }

    private function defaultQueueFor(ChannelConnection $connection): ?ServiceQueue
    {
        if ($connection->default_service_queue_id !== null) {
            return ServiceQueue::find($connection->default_service_queue_id);
        }

        return ServiceQueue::default();
    }
}
