<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Contracts\ConnectorEvent;
use App\Domain\Messaging\Enums\MessageType;
use Illuminate\Support\Carbon;

readonly class InboundMessage implements ConnectorEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalId,
        public ContactIdentity $contact,
        public MessageType $type = MessageType::Text,
        public ?string $body = null,
        public ?string $mediaUrl = null,
        public ?string $mediaName = null,
        public ?string $mediaMimeType = null,
        public ?string $replyToExternalId = null,
        public ?Carbon $sentAt = null,
        public bool $fromMe = false,
        public array $raw = [],
    ) {}

    public function occurredAt(): Carbon
    {
        return $this->sentAt ?? now();
    }

    public function preview(): string
    {
        return match (true) {
            filled($this->body) => (string) $this->body,
            $this->type->isMedia() => $this->type->label(),
            default => $this->type->label(),
        };
    }
}
