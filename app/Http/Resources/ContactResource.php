<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->displayName(),
            'legal_name' => $this->name,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'email' => $this->email,
            'document' => $this->document,
            'avatar_url' => $this->avatar_url,
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields,
            'is_blocked' => $this->is_blocked,
            'last_interaction_at' => $this->last_interaction_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'channels' => ContactChannelResource::collection($this->whenLoaded('channels')),
            'conversations_count' => $this->whenCounted('conversations'),
        ];
    }
}
