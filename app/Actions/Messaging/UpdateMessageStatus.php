<?php

namespace App\Actions\Messaging;

use App\Domain\Messaging\DataObjects\DeliveryStatusUpdate;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Events\Conversations\ConversationUpdated;
use App\Events\Conversations\MessageStatusUpdated;
use App\Models\ChannelConnection;
use App\Models\Message;

class UpdateMessageStatus
{
    public function handle(ChannelConnection $connection, DeliveryStatusUpdate $update): ?Message
    {
        $message = Message::query()
            ->where('external_id', $update->externalId)
            ->whereHas('conversation', fn ($query) => $query->where('channel_connection_id', $connection->id))
            ->first();

        if ($message === null || ! $this->applies($update->status, $message->status)) {
            return $message;
        }

        $message->forceFill($this->attributes($update, $message))->save();

        MessageStatusUpdated::dispatch($message);

        if ($update->status === MessageStatus::Deleted) {
            ConversationUpdated::dispatch($message->conversation);
        }

        return $message;
    }

    private function applies(MessageStatus $incoming, MessageStatus $current): bool
    {
        if ($incoming === MessageStatus::Failed) {
            return ! in_array(
                $current,
                [MessageStatus::Canceled, MessageStatus::Deleted, MessageStatus::Delivered, MessageStatus::Read],
                true,
            );
        }

        return $incoming->isAfter($current);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(DeliveryStatusUpdate $update, Message $message): array
    {
        $attributes = ['status' => $update->status, 'error' => $update->error];

        if (in_array($update->status, [MessageStatus::Delivered, MessageStatus::Read], true)) {
            $attributes['delivered_at'] = $message->delivered_at ?? $update->occurredAt();
        }

        if ($update->status === MessageStatus::Read) {
            $attributes['read_at'] = $message->read_at ?? $update->occurredAt();
        }

        return $attributes;
    }
}
