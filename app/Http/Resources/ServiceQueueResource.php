<?php

namespace App\Http\Resources;

use App\Models\ServiceQueue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceQueue
 */
class ServiceQueueResource extends JsonResource
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
            'color' => $this->color,
            'icon' => $this->icon,
            'priority' => $this->priority,
            'assignment_strategy' => $this->assignment_strategy,
            'business_hours' => $this->business_hours,
            'outside_hours_message' => $this->outside_hours_message,
            'ai_objective_id' => $this->ai_objective_id,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'is_open' => $this->isOpenAt(),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'conversations_count' => $this->whenCounted('conversations'),
        ];
    }
}
