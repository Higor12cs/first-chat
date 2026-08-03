<?php

namespace App\Actions\Conversations;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Events\Conversations\ConversationTransferred;
use App\Events\Conversations\ConversationUpdated;
use App\Jobs\Chatbot\AdvanceChatFlow;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;
use App\Services\Chatbot\FlowEngine;
use App\Services\Conversations\ConversationRouter;

class TransferConversation
{
    public function __construct(
        private readonly ConversationRouter $router,
        private readonly FlowEngine $engine,
    ) {}

    public function toManual(
        Conversation $conversation,
        ServiceQueue $queue,
        User $assignee,
        ?User $transferredBy = null,
    ): Conversation {
        $conversation = $this->router->release($conversation);
        $conversation = $this->router->moveToQueue($conversation, $queue, applyAutomations: false, announce: false);
        $conversation = $this->router->assign($conversation, $assignee, $transferredBy, announce: false);

        return $this->announce(
            $conversation,
            ConversationSection::Manual,
            queue: $queue,
            assignee: $assignee,
            transferredBy: $transferredBy,
        );
    }

    public function toWaiting(
        Conversation $conversation,
        ServiceQueue $queue,
        bool $applyAutomations = false,
        ?User $transferredBy = null,
    ): Conversation {
        $conversation = $this->router->release($conversation);
        $conversation = $this->router->moveToQueue($conversation, $queue, $applyAutomations, announce: false);

        return $this->announce(
            $conversation,
            $conversation->section(),
            queue: $queue,
            assignee: $conversation->assigned_user_id === null ? null : $conversation->assignedUser()->first(),
            transferredBy: $transferredBy,
        );
    }

    public function toAutomatic(
        Conversation $conversation,
        ChatFlow $flow,
        ?string $nodeId = null,
        ?User $transferredBy = null,
    ): Conversation {
        $conversation = $this->router->release($conversation);

        $this->engine->startAt($conversation, $flow, $nodeId);

        AdvanceChatFlow::dispatch($conversation);

        return $this->announce(
            $conversation,
            ConversationSection::Automatic,
            flow: $flow,
            transferredBy: $transferredBy,
        );
    }

    private function announce(
        Conversation $conversation,
        ConversationSection $section,
        ?ServiceQueue $queue = null,
        ?User $assignee = null,
        ?ChatFlow $flow = null,
        ?User $transferredBy = null,
    ): Conversation {
        ConversationTransferred::dispatch($conversation, $section, $queue, $assignee, $flow, $transferredBy);
        ConversationUpdated::dispatch($conversation);

        return $conversation;
    }
}
