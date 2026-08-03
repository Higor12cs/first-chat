<?php

namespace Database\Factories;

use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelConnection>
 */
class ChannelConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => fake()->company().' WhatsApp',
            'driver' => 'uazapi',
            'channel' => Channel::WhatsApp,
            'status' => ConnectionStatus::Connected,
            'credentials' => ['token' => 'instance-token'],
            'is_active' => true,
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConnectionStatus::Disconnected,
        ]);
    }
}
