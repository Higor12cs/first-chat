<?php

namespace App\Actions\Messaging;

use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Contracts\DownloadsMedia;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Domain\Messaging\DataObjects\MediaFile;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Events\Conversations\MessageReceived;
use App\Events\Conversations\MessageSent;
use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Conversations\ContactResolver;
use App\Services\Conversations\ConversationRouter;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReceiveInboundMessage
{
    public function __construct(
        private readonly ContactResolver $contacts,
        private readonly ConversationRouter $router,
        private readonly ConnectorManager $connectors,
    ) {}

    public function handle(ChannelConnection $connection, InboundMessage $inbound): ?Message
    {
        $existing = $this->stored($connection, $inbound);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $message = $this->store($connection, $inbound);
        } catch (UniqueConstraintViolationException) {
            return $this->stored($connection, $inbound);
        }

        $inbound->fromMe
            ? MessageSent::dispatch($message->conversation, $message)
            : MessageReceived::dispatch($message->conversation, $message);

        return $message;
    }

    private function stored(ChannelConnection $connection, InboundMessage $inbound): ?Message
    {
        return Message::query()
            ->where('external_id', $inbound->externalId)
            ->whereHas('conversation', fn ($query) => $query->where('channel_connection_id', $connection->id))
            ->first();
    }

    private function media(ChannelConnection $connection, InboundMessage $inbound): ?MediaFile
    {
        if (! $inbound->type->isMedia()) {
            return null;
        }

        $connector = $this->connectors->for($connection);

        if (! $connector instanceof DownloadsMedia) {
            return null;
        }

        return $connector->downloadMedia($inbound->externalId);
    }

    private function store(ChannelConnection $connection, InboundMessage $inbound): Message
    {
        $media = $this->media($connection, $inbound);

        return DB::transaction(function () use ($connection, $inbound, $media): Message {
            $contactChannel = $this->contacts->resolve($connection, $inbound->contact);
            $conversation = $this->router->resolveOpenConversation(
                $connection,
                $contactChannel,
                inbound: ! $inbound->fromMe,
            );

            $message = Message::create([
                'tenant_id' => $connection->tenant_id,
                'conversation_id' => $conversation->id,
                'reply_to_message_id' => $this->quoted($conversation, $inbound),
                'external_id' => $inbound->externalId,
                'direction' => $inbound->fromMe ? MessageDirection::Outbound : MessageDirection::Inbound,
                'type' => $inbound->type,
                'status' => $inbound->fromMe ? MessageStatus::Sent : MessageStatus::Delivered,
                'source' => $inbound->fromMe ? MessageSource::System : MessageSource::Contact,
                'body' => $inbound->body,
                'media_url' => $media?->url ?? $inbound->mediaUrl,
                'media_name' => $media?->name ?? $inbound->mediaName,
                'media_mime_type' => $media?->mimeType ?? $inbound->mediaMimeType,
                'metadata' => ['raw' => $inbound->raw],
                'sent_at' => $inbound->occurredAt(),
                'delivered_at' => $inbound->fromMe ? null : now(),
            ]);

            $this->touchConversation($conversation, $inbound);

            return $message->setRelation('conversation', $conversation);
        });
    }

    private function quoted(Conversation $conversation, InboundMessage $inbound): ?string
    {
        if (blank($inbound->replyToExternalId)) {
            return null;
        }

        return Message::query()
            ->where('external_id', $inbound->replyToExternalId)
            ->whereHas('conversation', fn (Builder $query) => $conversation->contact_channel_id === null
                ? $query->whereKey($conversation->id)
                : $query->where('contact_channel_id', $conversation->contact_channel_id))
            ->value('id');
    }

    private function touchConversation(Conversation $conversation, InboundMessage $inbound): void
    {
        if ($inbound->fromMe) {
            $conversation->forceFill([
                'last_message_at' => $inbound->occurredAt(),
                'first_response_at' => $conversation->first_response_at ?? $inbound->occurredAt(),
            ])->save();

            return;
        }

        $conversation->forceFill([
            'last_message_at' => $inbound->occurredAt(),
            'last_inbound_at' => $inbound->occurredAt(),
            'unread_count' => $conversation->unread_count + 1,
        ])->save();

        $conversation->contact()->update(['last_interaction_at' => now()]);
    }
}
