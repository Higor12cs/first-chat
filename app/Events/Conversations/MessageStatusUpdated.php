<?php

namespace App\Events\Conversations;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversations.{$this->message->conversation->id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'status' => $this->message->status->value,
            'status_label' => $this->message->status->label(),
            'delivered_at' => $this->message->delivered_at?->toIso8601String(),
            'read_at' => $this->message->read_at?->toIso8601String(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
