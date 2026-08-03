<?php

namespace Database\Factories;

use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Domain\Tenancy\TenantContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'conversation_id' => Conversation::factory(),
            'direction' => MessageDirection::Inbound,
            'type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'source' => MessageSource::Contact,
            'body' => fake()->sentence(),
            'sent_at' => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => MessageDirection::Outbound,
            'source' => MessageSource::Agent,
            'status' => MessageStatus::Sent,
        ]);
    }
}
