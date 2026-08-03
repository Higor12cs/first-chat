<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Contracts\ManagesSession;
use App\Domain\Messaging\Contracts\ProvisionsInstance;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncConnectionStatus implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 1;

    public function __construct(public ChannelConnection $channelConnection)
    {
        $this->onQueue('maintenance');
    }

    public function handle(ConnectorManager $connectors, ConnectionStatusSynchronizer $synchronizer): void
    {
        $this->forTenant(
            $this->channelConnection->tenant,
            fn () => $this->sync($connectors, $synchronizer),
        );
    }

    private function sync(ConnectorManager $connectors, ConnectionStatusSynchronizer $synchronizer): void
    {
        $connector = $connectors->for($this->channelConnection);

        if (! $connector instanceof ManagesSession) {
            return;
        }

        if ($connector instanceof ProvisionsInstance && ! $connector->isProvisioned()) {
            return;
        }

        try {
            $update = $connector->status();

            $synchronizer->apply($this->channelConnection, $update);

            if (data_get($update->metadata, 'token_rejected') === true) {
                $this->reprovision();
            }
        } catch (Throwable $exception) {
            Log::warning('Could not read the channel status.', [
                'connection_id' => $this->channelConnection->id,
                'tenant_id' => $this->channelConnection->tenant_id,
                'driver' => $this->channelConnection->driver,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function reprovision(): void
    {
        Log::warning('Credential refused by the provider, rebuilding the instance.', [
            'connection_id' => $this->channelConnection->id,
            'tenant_id' => $this->channelConnection->tenant_id,
            'driver' => $this->channelConnection->driver,
        ]);

        $this->channelConnection->forceFill([
            'credentials' => [],
            'external_identifier' => null,
            'qr_code' => null,
            'pair_code' => null,
        ])->save();

        ProvisionConnection::dispatch($this->channelConnection);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->channelConnection->tenant_id];
    }
}
