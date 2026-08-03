<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\QuickReply;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickReply>
 */
class QuickReplyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'title' => fake()->sentence(3),
            'shortcut' => '/'.fake()->unique()->word(),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(['Saudações', 'Comercial', 'Suporte']),
        ];
    }
}
