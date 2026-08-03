<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantContext::class)->id() ?? Tenant::factory(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('55###########'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
