<?php

namespace App\Http\Resources;

use App\Models\ConversationNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationNote
 */
class ConversationNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
