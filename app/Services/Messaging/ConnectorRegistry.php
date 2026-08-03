<?php

namespace App\Services\Messaging;

use App\Domain\Messaging\DataObjects\ConnectorDefinition;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Exceptions\ConnectorException;
use Illuminate\Support\Collection;

class ConnectorRegistry
{
    /**
     * @var Collection<string, ConnectorDefinition>|null
     */
    private ?Collection $definitions = null;

    /**
     * @return Collection<string, ConnectorDefinition>
     */
    public function all(): Collection
    {
        return $this->definitions ??= collect(config('connectors.drivers', []))
            ->map(fn (array $config, string $key): ConnectorDefinition => ConnectorDefinition::fromConfig($key, $config));
    }

    public function definition(string $driver): ConnectorDefinition
    {
        return $this->all()->get($driver) ?? throw ConnectorException::unknownDriver($driver);
    }

    public function has(string $driver): bool
    {
        return $this->all()->has($driver);
    }

    /**
     * @return Collection<string, ConnectorDefinition>
     */
    public function forChannel(Channel $channel): Collection
    {
        return $this->all()->filter(
            fn (ConnectorDefinition $definition): bool => $definition->channel === $channel
        );
    }

    public function provisioningDriver(Channel $channel): ?ConnectorDefinition
    {
        $driver = config("connectors.provisioning.{$channel->value}");

        return $driver === null ? null : $this->definition($driver);
    }

    /**
     * @return array<int, Channel>
     */
    public function tenantChannels(): array
    {
        return collect(config('connectors.tenant_channels', []))
            ->map(fn (string $channel): Channel => Channel::from($channel))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->all()->map->toArray()->values()->all();
    }
}
