<?php

namespace App\Domain\Messaging\Exceptions;

use RuntimeException;

class ConnectorException extends RuntimeException
{
    public static function unknownDriver(string $driver): self
    {
        return new self("Connector [{$driver}] is not registered in config/connectors.php.");
    }

    public static function unsupported(string $driver, string $feature): self
    {
        return new self("Connector [{$driver}] does not support [{$feature}].");
    }

    public static function requestFailed(string $driver, string $reason): self
    {
        return new self("Connector [{$driver}] request failed: {$reason}");
    }
}
