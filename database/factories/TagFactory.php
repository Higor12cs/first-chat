<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tag;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'color' => fake()->randomElement(['primary', 'success', 'warning', 'danger', 'info']),
        ];
    }
}
