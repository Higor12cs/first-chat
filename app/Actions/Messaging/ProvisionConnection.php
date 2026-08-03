<?php

namespace App\Actions\Messaging;

use App\Domain\Messaging\Contracts\ProvisionsInstance;
use App\Domain\Messaging\Exceptions\ConnectorException;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Log;

class ProvisionConnection
{
    public function __construct(private readonly ConnectorManager $connectors) {}

    public function handle(ChannelConnection $connection): bool
    {
        $connector = $this->connectors->for($connection);

        if (! $connector instanceof ProvisionsInstance || $connector->isProvisioned()) {
            return true;
        }

        try {
            $provisioning = $connector->provision();
        } catch (ConnectorException $exception) {
            Log::error('Failed to provision the connection.', [
                'connection_id' => $connection->id,
                'tenant_id' => $connection->tenant_id,
                'driver' => $connection->driver,
                'error' => $exception->getMessage(),
            ]);

            $connection->forceFill(['last_error' => $exception->getMessage()])->save();

            return false;
        }

        $connection->forceFill([
            'credentials' => [...$connection->credentials ?? [], ...$provisioning->credentials],
            'external_identifier' => $provisioning->externalIdentifier ?? $connection->external_identifier,
            'last_error' => null,
        ])->save();

        return true;
    }
}
