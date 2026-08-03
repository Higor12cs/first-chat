<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\AiObjective;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiObjective>
 */
class AiObjectiveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 1024,
            'system_prompt' => 'Você é um atendente virtual cordial e objetivo.',
            'tools' => ['request_human'],
            'max_turns' => 20,
            'is_active' => true,
        ];
    }
}
