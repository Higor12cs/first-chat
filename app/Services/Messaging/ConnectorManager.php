<?php

namespace App\Services\Messaging;

use App\Domain\Messaging\Contracts\MessagingConnector;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Container\Container;

class ConnectorManager
{
    /**
     * @var array<int, MessagingConnector>
     */
    private array $resolved = [];

    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly Container $container,
    ) {}

    public function for(ChannelConnection $connection): MessagingConnector
    {
        return $this->resolved[$connection->id] ??= $this->container->make(
            $this->registry->definition($connection->driver)->class,
            ['connection' => $connection],
        );
    }

    public function forget(ChannelConnection $connection): void
    {
        unset($this->resolved[$connection->id]);
    }
}
