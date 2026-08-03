<?php

namespace App\Services\Messaging;

use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Events\Messaging\ConnectorConnected;
use App\Events\Messaging\ConnectorStatusChanged;
use App\Models\ChannelConnection;

class ConnectionStatusSynchronizer
{
    public function apply(ChannelConnection $connection, ConnectionStatusUpdate $update): ChannelConnection
    {
        $wasConnected = $connection->status === ConnectionStatus::Connected;

        $connection->forceFill([
            'status' => $update->status,
            'qr_code' => $update->status === ConnectionStatus::Connecting ? $update->qrCode : null,
            'pair_code' => $update->status === ConnectionStatus::Connecting ? $update->pairCode : null,
            'external_identifier' => $update->externalIdentifier ?? $connection->external_identifier,
            'last_connected_at' => $update->status === ConnectionStatus::Connected ? now() : $connection->last_connected_at,
            'last_error' => data_get($update->metadata, 'error'),
        ])->save();

        if ($update->status === ConnectionStatus::Connected && ! $wasConnected) {
            ConnectorConnected::dispatch($connection);
        }

        ConnectorStatusChanged::dispatch($connection);

        return $connection;
    }
}
