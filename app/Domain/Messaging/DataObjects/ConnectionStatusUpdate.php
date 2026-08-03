<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Contracts\ConnectorEvent;
use App\Domain\Messaging\Enums\ConnectionStatus;
use Illuminate\Support\Carbon;

readonly class ConnectionStatusUpdate implements ConnectorEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ConnectionStatus $status,
        public ?string $qrCode = null,
        public ?string $pairCode = null,
        public ?string $externalIdentifier = null,
        public array $metadata = [],
        public ?Carbon $happenedAt = null,
    ) {}

    public function occurredAt(): Carbon
    {
        return $this->happenedAt ?? now();
    }
}
