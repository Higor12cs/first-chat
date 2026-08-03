<?php

namespace App\Http\Resources;

use App\Models\ChatFlow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatFlow
 */
class ChatFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'nodes' => $this->nodes ?? [],
            'edges' => $this->edges ?? [],
            'triggers' => $this->triggers ?? [],
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
