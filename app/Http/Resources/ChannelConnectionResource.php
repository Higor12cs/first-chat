<?php

namespace App\Http\Resources;

use App\Models\ChannelConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChannelConnection
 */
class ChannelConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $definition = $this->definition();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'channel_color' => $this->channel->color(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'external_identifier' => $this->external_identifier,
            'qr_code' => $this->qr_code,
            'pair_code' => $this->pair_code,
            'has_error' => filled($this->last_error),
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'default_service_queue_id' => $this->default_service_queue_id,
            'chat_flow_id' => $this->chat_flow_id,
            'is_active' => $this->is_active,
            'capabilities' => $definition->capabilities->toArray(),
            'settings' => $this->settings ?? [],
            'conversations_count' => $this->whenCounted('conversations'),
        ];
    }
}
