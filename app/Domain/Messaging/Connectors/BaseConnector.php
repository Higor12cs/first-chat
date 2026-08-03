<?php

namespace App\Domain\Messaging\Connectors;

use App\Domain\Messaging\Contracts\MessagingConnector;
use App\Domain\Messaging\DataObjects\ConnectorCapabilities;
use App\Models\ChannelConnection;

abstract class BaseConnector implements MessagingConnector
{
    public function __construct(protected readonly ChannelConnection $connection) {}

    public function capabilities(): ConnectorCapabilities
    {
        return $this->connection->definition()->capabilities;
    }

    protected function driver(): string
    {
        return $this->connection->driver;
    }

    protected function credential(string $key, mixed $default = null): mixed
    {
        return $this->connection->credential($key)
            ?? data_get($this->connection->definition()->credentials, $key, $default);
    }
}
