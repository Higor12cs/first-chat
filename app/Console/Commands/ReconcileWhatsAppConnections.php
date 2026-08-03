<?php

namespace App\Console\Commands;

use App\Domain\Messaging\Connectors\Uazapi\UazapiAdminClient;
use App\Domain\Tenancy\TenantContext;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcileWhatsAppConnections extends Command
{
    protected $signature = 'whatsapp:reconcile {--prune : Delete orphan instances instead of only reporting them}';

    protected $description = 'Reconcile provider instances with stored connections, flagging (or pruning) orphans so they stop being billed';

    private const MIN_AGE_MINUTES = 0;

    public function handle(UazapiAdminClient $admin, TenantContext $context): int
    {
        if (! $admin->isConfigured()) {
            $this->error('Provedor não configurado (UAZAPI_BASE_URL / UAZAPI_ADMIN_TOKEN).');

            return self::FAILURE;
        }

        ['lookup' => $known, 'connections' => $connections] = $this->knownInstances($context);

        $ours = 0;
        $orphans = 0;
        $pruned = 0;

        foreach ($admin->listInstances() as $instance) {
            if (! $this->belongsToApp($instance)) {
                continue;
            }

            $ours++;

            $id = (string) ($instance['id'] ?? '');
            $name = (string) ($instance['name'] ?? '');
            $token = (string) ($instance['token'] ?? '');

            if (isset($known[$id]) || isset($known[$name])) {
                continue;
            }

            if ($this->tooYoung($instance)) {
                continue;
            }

            $orphans++;
            $this->warn("Órfã: {$id} (".($name ?: 'sem nome').')');

            if ($this->option('prune') && $token !== '' && $admin->deleteInstance($token)) {
                $pruned++;
            }
        }

        $this->info(sprintf(
            'Instâncias do app: %d | conexões cadastradas: %d | órfãs: %d | removidas: %d',
            $ours,
            $connections,
            $orphans,
            $pruned,
        ));

        if ($orphans > 0 && ! $this->option('prune')) {
            $this->comment('Nada foi removido: rode com --prune para apagar as órfãs no provedor.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    private function belongsToApp(array $instance): bool
    {
        $prefix = $this->prefix();

        if ($prefix === '') {
            return false;
        }

        if ((string) ($instance['adminField01'] ?? '') === $prefix) {
            return true;
        }

        return str_starts_with((string) ($instance['name'] ?? ''), $prefix.'-');
    }

    /**
     * @return array{lookup: array<string, true>, connections: int}
     */
    private function knownInstances(TenantContext $context): array
    {
        $lookup = [];
        $connections = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($context, &$lookup, &$connections): void {
            $context->run($tenant, function () use (&$lookup, &$connections): void {
                ChannelConnection::query()
                    ->where('driver', 'uazapi')
                    ->cursor()
                    ->each(function (ChannelConnection $connection) use (&$lookup, &$connections): void {
                        $connections++;

                        foreach (['instance_id', 'instance_name'] as $key) {
                            $value = (string) $connection->credential($key, '');

                            if ($value !== '') {
                                $lookup[$value] = true;
                            }
                        }
                    });
            });
        });

        return ['lookup' => $lookup, 'connections' => $connections];
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    private function tooYoung(array $instance): bool
    {
        $created = $instance['created'] ?? $instance['adminField02'] ?? null;

        if (! is_string($created) || $created === '') {
            return false;
        }

        return Carbon::parse($created)->greaterThan(now()->subMinutes(self::MIN_AGE_MINUTES));
    }

    private function prefix(): string
    {
        return (string) config('connectors.instance_prefix');
    }
}
