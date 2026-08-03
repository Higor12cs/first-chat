<?php

namespace Database\Factories;

use App\Domain\Messaging\Enums\Channel;
use App\Domain\Tenancy\TenantContext;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactChannel>
 */
class ContactChannelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'contact_id' => Contact::factory(),
            'channel_connection_id' => ChannelConnection::factory(),
            'channel' => Channel::WhatsApp,
            'identifier' => fake()->unique()->numerify('55###########@s.whatsapp.net'),
            'display_name' => fake()->name(),
        ];
    }
}
