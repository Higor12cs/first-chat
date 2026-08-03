<?php

namespace Database\Factories;

use App\Domain\Tenancy\TenantContext;
use App\Models\ChatFlow;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatFlow>
 */
class ChatFlowFactory extends Factory
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
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'position' => ['x' => 80, 'y' => 80], 'data' => []],
                ['id' => 'greeting', 'type' => 'message', 'position' => ['x' => 320, 'y' => 80], 'data' => ['text' => 'Olá! Como podemos ajudar?']],
            ],
            'edges' => [
                ['id' => 'start-greeting', 'source' => 'start', 'target' => 'greeting', 'sourceHandle' => null],
            ],
            'is_active' => true,
        ];
    }
}
