<?php

namespace Database\Factories;

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Tenancy\TenantContext;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'contact_id' => Contact::factory(),
            'contact_channel_id' => ContactChannel::factory(),
            'channel_connection_id' => ChannelConnection::factory(),
            'channel' => Channel::WhatsApp,
            'status' => ConversationStatus::Pending,
            'last_message_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
