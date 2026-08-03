<?php

namespace App\Actions\Messaging;

use App\Domain\Messaging\DataObjects\ConnectorDefinition;
use App\Domain\Messaging\Enums\Channel;
use App\Jobs\Messaging\ProvisionConnection as ProvisionConnectionJob;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use App\Services\Messaging\ConnectorRegistry;
use Illuminate\Support\Collection;

class ProvisionTenantConnections
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    /**
     * @return Collection<int, ChannelConnection>
     */
    public function handle(Tenant $tenant): Collection
    {
        return collect($this->registry->tenantChannels())
            ->flatMap(fn (Channel $channel): array => $this->forChannel($tenant, $channel))
            ->values();
    }

    /**
     * @return array<int, ChannelConnection>
     */
    private function forChannel(Tenant $tenant, Channel $channel): array
    {
        $definition = $this->registry->provisioningDriver($channel);

        if ($definition === null) {
            return [];
        }

        $existing = ChannelConnection::query()
            ->acrossTenants()
            ->where('tenant_id', $tenant->id)
            ->where('channel', $channel->value)
            ->count();

        $created = [];

        for ($position = $existing; $position < $this->target($tenant, $channel); $position++) {
            $created[] = $this->create($tenant, $channel, $definition, $position + 1);
        }

        return $created;
    }

    private function target(Tenant $tenant, Channel $channel): int
    {
        if ($channel !== Channel::WhatsApp) {
            return 1;
        }

        return max(1, $tenant->limit('max_connections') ?? 1);
    }

    private function create(Tenant $tenant, Channel $channel, ConnectorDefinition $definition, int $position): ChannelConnection
    {
        $connection = ChannelConnection::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $position === 1 ? $channel->label() : "{$channel->label()} {$position}",
            'driver' => $definition->key,
            'channel' => $channel,
            'is_active' => true,
        ]);

        ProvisionConnectionJob::dispatch($connection);

        return $connection;
    }
}
