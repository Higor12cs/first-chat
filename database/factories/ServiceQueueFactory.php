<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceQueue>
 */
class ServiceQueueFactory extends Factory
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
            'color' => 'primary',
            'priority' => fake()->numberBetween(0, 20),
            'assignment_strategy' => 'manual',
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }
}
