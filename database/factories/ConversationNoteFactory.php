<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationNote>
 */
class ConversationNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
