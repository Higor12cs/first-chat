<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
