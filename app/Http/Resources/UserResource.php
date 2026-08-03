<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->membership();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active && ($membership?->is_active ?? true),
            'is_super_admin' => $this->is_super_admin,
            'hides_other_conversations' => $this->hides_other_conversations,
            'signs_messages' => $this->signs_messages,
            'work_days' => $this->work_days,
            'work_starts_at' => $this->work_starts_at,
            'work_ends_at' => $this->work_ends_at,
            'auto_lock_minutes' => $this->auto_lock_minutes,
            'blocked_until' => $this->blocked_until?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'tenants' => $this->whenLoaded('tenants', fn (): array => $this->tenants
                ->map(fn ($tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])
                ->all()),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'service_queues' => ServiceQueueResource::collection($this->whenLoaded('serviceQueues')),
        ];
    }
}
