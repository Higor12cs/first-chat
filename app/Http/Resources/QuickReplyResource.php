<?php

namespace App\Http\Resources;

use App\Models\QuickReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuickReply
 */
class QuickReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'shortcut' => $this->shortcut,
            'title' => $this->title,
            'body' => $this->body,
            'is_favorite' => $this->is_favorite,
            'usage_count' => $this->usage_count,
            'is_shared' => $this->user_id === null,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
