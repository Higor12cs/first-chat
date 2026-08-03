<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\AiInteraction;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\Ai\AiCostCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiInteraction>
 */
class AiInteractionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'conversation_id' => Conversation::factory(),
            'ai_objective_id' => AiObjective::factory(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'status' => 'completed',
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(50, 800),
            'cost_micro_cents' => fake()->numberBetween(0, 10 * AiCostCalculator::MICRO_CENTS_PER_CENT),
            'latency_ms' => fake()->numberBetween(200, 4000),
        ];
    }
}
