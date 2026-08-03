<?php

namespace Database\Seeders;

use App\Models\AiObjective;
use Illuminate\Database\Seeder;

class AiObjectiveSeeder extends Seeder
{
    /**
     * @param  array<int, array<string, mixed>>  $objectives
     */
    public function run(array $objectives = []): void
    {
        foreach ($objectives as $objective) {
            AiObjective::query()->updateOrCreate(['slug' => $objective['slug']], $objective);
        }
    }
}
