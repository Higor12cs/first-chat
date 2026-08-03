<?php

namespace App\Http\Resources;

use App\Models\ContactChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactChannel
 */
class ContactChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'identifier' => $this->identifier,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'is_group' => $this->is_group,
            'connection' => ChannelConnectionResource::make($this->whenLoaded('connection')),
        ];
    }
}
