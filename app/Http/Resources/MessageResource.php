<?php

namespace App\Http\Resources;

use App\Domain\Messaging\Enums\MessageDirection;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'external_id' => $this->external_id,
            'direction' => $this->direction->value,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'is_internal' => (bool) $this->is_internal,
            'body' => $this->body,
            'media_url' => $this->mediaUrlForBrowser(),
            'media_name' => $this->media_name,
            'media_mime_type' => $this->media_mime_type,
            'transcription' => $this->transcription,
            'reply_to_message_id' => $this->reply_to_message_id,
            'reply_to' => $this->whenLoaded('replyTo', fn (): ?array => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'body' => $this->replyTo->body,
                'direction' => $this->replyTo->direction->value,
                'type_label' => $this->replyTo->type->label(),
                'author' => $this->replyTo->direction === MessageDirection::Outbound
                    ? ($this->replyTo->user?->name ?? 'Você')
                    : null,
            ]),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
