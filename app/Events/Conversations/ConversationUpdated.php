<?php

namespace App\Events\Conversations;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

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
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation' => ConversationResource::make(
                $this->conversation->loadMissing(['contact', 'connection', 'serviceQueue', 'assignedUser', 'tags', 'lastMessage'])
            )->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
