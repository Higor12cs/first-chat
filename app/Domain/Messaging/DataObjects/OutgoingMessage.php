<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Enums\MessageType;

readonly class OutgoingMessage
{
    /**
     * @param  array<int, array{id: string, label: string}>  $buttons
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $recipient,
        public MessageType $type = MessageType::Text,
        public ?string $body = null,
        public ?string $mediaUrl = null,
        public ?string $mediaName = null,
        public ?string $mediaMimeType = null,
        public ?string $replyToExternalId = null,
        public array $buttons = [],
        public array $payload = [],
    ) {}

    public static function text(string $recipient, string $body, ?string $replyToExternalId = null): self
    {
        return new self(
            recipient: $recipient,
            type: MessageType::Text,
            body: $body,
            replyToExternalId: $replyToExternalId,
        );
    }

    /**
     * @param  array<int, array{id: string, label: string}>  $buttons
     */
    public static function buttons(string $recipient, string $body, array $buttons): self
    {
        return new self(
            recipient: $recipient,
            type: MessageType::Interactive,
            body: $body,
            buttons: $buttons,
        );
    }
}
