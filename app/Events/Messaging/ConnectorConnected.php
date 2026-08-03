<?php

namespace App\Events\Messaging;

use App\Http\Resources\ChannelConnectionResource;
use App\Models\ChannelConnection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectorConnected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChannelConnection $channelConnection) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenants.{$this->channelConnection->tenant_id}.connections")];
    }

    public function broadcastAs(): string
    {
        return 'connector.connected';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['connection' => ChannelConnectionResource::make($this->channelConnection)->resolve()];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
