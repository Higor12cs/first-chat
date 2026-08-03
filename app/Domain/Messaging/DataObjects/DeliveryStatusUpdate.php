<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Contracts\ConnectorEvent;
use App\Domain\Messaging\Enums\MessageStatus;
use Illuminate\Support\Carbon;

readonly class DeliveryStatusUpdate implements ConnectorEvent
{
    public function __construct(
        public string $externalId,
        public MessageStatus $status,
        public ?Carbon $happenedAt = null,
        public ?string $error = null,
    ) {}

    public function occurredAt(): Carbon
    {
        return $this->happenedAt ?? now();
    }
}
