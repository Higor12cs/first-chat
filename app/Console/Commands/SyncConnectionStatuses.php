<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\Messaging\SyncConnectionStatus;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncConnectionStatuses extends Command
{
    private const SPREAD_SECONDS = 45;

    protected $signature = 'connections:sync';

    protected $description = 'Queue a status read for every active connection so a disconnection made on the phone reaches the interface on its own';

    public function handle(TenantContext $context): int
    {
        $queued = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($context, &$queued): void {
            $context->run($tenant, function () use (&$queued): void {
                ChannelConnection::query()
                    ->where('is_active', true)
                    ->where('status', '!=', ConnectionStatus::Connecting->value)
                    ->cursor()
                    ->each(function (ChannelConnection $connection) use (&$queued): void {
                        SyncConnectionStatus::dispatch($connection)
                            ->delay(now()->addSeconds($queued % self::SPREAD_SECONDS));

                        $queued++;
                    });
            });
        });

        $this->info("Conexões enfileiradas para verificação: {$queued}.");

        return self::SUCCESS;
    }
}
