<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Enums\MessageStatus;

readonly class MessageResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $externalId = null,
        public MessageStatus $status = MessageStatus::Sent,
        public ?string $error = null,
        public array $raw = [],
        public bool $retryable = false,
    ) {}

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(status: MessageStatus::Failed, error: $error, retryable: $retryable);
    }

    public function successful(): bool
    {
        return $this->status !== MessageStatus::Failed;
    }
}
