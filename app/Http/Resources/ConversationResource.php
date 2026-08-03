<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'section' => $this->section()->value,
            'section_label' => $this->section()->label(),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'channel_color' => $this->channel->color(),
            'subject' => $this->subject,
            'is_group' => $this->is_group,
            'priority' => $this->priority,
            'unread_count' => $this->unread_count,
            'no_action_at' => $this->no_action_at?->toIso8601String(),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'contact' => ContactResource::make($this->whenLoaded('contact')),
            'contact_channel' => ContactChannelResource::make($this->whenLoaded('contactChannel')),
            'connection' => $this->whenLoaded('connection', fn (): ?array => $this->connection === null ? null : [
                'id' => $this->connection->id,
                'name' => $this->connection->name,
                'status' => $this->connection->status->value,
                'status_label' => $this->connection->status->label(),
            ]),
            'service_queue' => $this->whenLoaded('serviceQueue', fn (): ?array => $this->serviceQueue === null ? null : [
                'id' => $this->serviceQueue->id,
                'name' => $this->serviceQueue->name,
            ]),
            'assigned_user' => $this->whenLoaded('assignedUser', fn (): ?array => $this->assignedUser === null ? null : [
                'id' => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
                'avatar_url' => $this->assignedUser->avatar_url,
            ]),
            'ai_objective' => $this->whenLoaded('aiObjective', fn (): ?array => $this->aiObjective === null ? null : [
                'id' => $this->aiObjective->id,
                'name' => $this->aiObjective->name,
            ]),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'notes' => ConversationNoteResource::collection($this->whenLoaded('notes')),
            'last_message' => $this->whenLoaded('lastMessage', fn (): ?array => $this->lastMessage === null ? null : [
                'id' => $this->lastMessage->id,
                'direction' => $this->lastMessage->direction->value,
                'status' => $this->lastMessage->status->value,
                'type_label' => $this->lastMessage->type->label(),
                'body' => $this->lastMessage->body === null
                    ? null
                    : Str::limit($this->lastMessage->body, 180),
            ]),
        ];
    }
}
