<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Contracts\ManagesSession;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Exceptions\ConnectorException;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshPairingSession implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public const INTERVAL = 20;

    public const MAX_ATTEMPTS = 15;

    public int $tries = 1;

    public function __construct(
        public ChannelConnection $channelConnection,
        public int $attempt = 1,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(ConnectorManager $connectors, ConnectionStatusSynchronizer $synchronizer): void
    {
        $this->forTenant($this->channelConnection->tenant, function () use ($connectors, $synchronizer): void {
            $connection = $this->channelConnection->fresh();

            if ($connection === null || $connection->status !== ConnectionStatus::Connecting) {
                return;
            }

            $connector = $connectors->for($connection);

            if (! $connector instanceof ManagesSession) {
                return;
            }

            try {
                $update = $synchronizer->apply($connection, $connector->status());
            } catch (ConnectorException $exception) {
                Log::warning('Could not refresh the channel pairing.', [
                    'connection_id' => $connection->id,
                    'driver' => $connection->driver,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            if ($update->status === ConnectionStatus::Connecting && $this->attempt < self::MAX_ATTEMPTS) {
                self::dispatch($connection, $this->attempt + 1)->delay(now()->addSeconds(self::INTERVAL));
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->channelConnection->tenant_id];
    }
}
