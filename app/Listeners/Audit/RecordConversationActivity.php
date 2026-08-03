<?php

namespace App\Listeners\Audit;

use App\Events\Conversations\ConversationAssigned;
use App\Events\Conversations\ConversationClosed;
use App\Events\Conversations\ConversationQueued;
use App\Events\Conversations\ConversationTransferred;
use App\Models\AuditLog;
use App\Models\Conversation;

class RecordConversationActivity
{
    public function recordAssigned(ConversationAssigned $event): void
    {
        $this->log($event->conversation, 'conversation.assigned', [
            'assignee' => $event->assignee?->name,
            'assigned_by' => $event->assignedBy?->name,
        ], $event->assignedBy?->id);
    }

    public function recordTransferred(ConversationTransferred $event): void
    {
        $this->log($event->conversation, 'conversation.transferred', [
            'section' => $event->section->value,
            'queue' => $event->serviceQueue?->name,
            'assignee' => $event->assignee?->name,
            'flow' => $event->chatFlow?->name,
            'transferred_by' => $event->transferredBy?->name,
        ], $event->transferredBy?->id);
    }

    public function recordQueued(ConversationQueued $event): void
    {
        $this->log($event->conversation, 'conversation.queued', [
            'queue' => $event->serviceQueue->name,
        ], null);
    }

    public function recordClosed(ConversationClosed $event): void
    {
        $this->log($event->conversation, 'conversation.closed', [
            'closed_by' => $event->closedBy?->name,
        ], $event->closedBy?->id);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ConversationAssigned::class => 'recordAssigned',
            ConversationQueued::class => 'recordQueued',
            ConversationTransferred::class => 'recordTransferred',
            ConversationClosed::class => 'recordClosed',
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Conversation $conversation, string $action, array $properties, ?string $userId): void
    {
        AuditLog::create([
            'tenant_id' => $conversation->tenant_id,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $conversation->getMorphClass(),
            'auditable_id' => $conversation->id,
            'properties' => array_filter($properties),
        ]);
    }
}
