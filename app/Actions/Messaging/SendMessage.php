<?php

namespace App\Actions\Messaging;

use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Conversations\MessageSent;
use App\Jobs\Messaging\DeliverMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class SendMessage
{
    /**
     * @param  array<int, array{id: string, label: string}>  $buttons
     * @param  bool|null  $sign  Overrides the signature preference for this message alone.
     */
    public function handle(
        Conversation $conversation,
        ?string $body,
        MessageSource $source = MessageSource::Agent,
        ?User $user = null,
        MessageType $type = MessageType::Text,
        ?string $mediaUrl = null,
        ?string $mediaName = null,
        ?string $mediaMimeType = null,
        ?Message $replyTo = null,
        array $buttons = [],
        bool $internal = false,
        ?bool $sign = null,
    ): Message {
        if ($source === MessageSource::Agent && ! $internal) {
            $body = $this->sign($body, $conversation, $user, $sign);
        }

        $message = Message::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'user_id' => $user?->id,
            'reply_to_message_id' => $replyTo?->id,
            'direction' => MessageDirection::Outbound,
            'type' => $type,
            'status' => $internal ? MessageStatus::Sent : MessageStatus::Pending,
            'source' => $source,
            'is_internal' => $internal,
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_name' => $mediaName,
            'media_mime_type' => $mediaMimeType,
            'metadata' => $buttons === [] ? null : ['buttons' => $buttons],
            'sent_at' => now(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'first_response_at' => $internal
                ? $conversation->first_response_at
                : ($conversation->first_response_at ?? now()),
        ])->save();

        MessageSent::dispatch($conversation, $message);

        if (! $internal) {
            DeliverMessage::dispatch($message);
        }

        return $message;
    }

    private function sign(?string $body, Conversation $conversation, ?User $user, ?bool $sign): ?string
    {
        if (blank($body) || $user === null) {
            return $body;
        }

        $signs = $sign
            ?? $user->signs_messages
            ?? (bool) ($conversation->tenant?->settings['sign_messages'] ?? false);

        return $signs ? "#*_{$user->name}:_*\n{$body}" : $body;
    }
}
