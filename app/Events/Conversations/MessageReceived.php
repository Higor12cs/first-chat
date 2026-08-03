<?php

namespace App\Events\Conversations;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenants.{$this->conversation->tenant_id}.conversations"),
            new PrivateChannel("conversations.{$this->conversation->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => MessageResource::make($this->message->loadMissing(['user', 'replyTo.user']))->resolve(),
            'conversation' => ConversationResource::make(
                $this->conversation->loadMissing(['contact', 'connection', 'serviceQueue', 'assignedUser', 'tags'])
            )->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
