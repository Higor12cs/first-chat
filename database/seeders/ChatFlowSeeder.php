<?php

namespace Database\Seeders;

use App\Models\ChatFlow;
use Illuminate\Database\Seeder;

class ChatFlowSeeder extends Seeder
{
    /**
     * @param  array<int, array<string, mixed>>  $flows
     */
    public function run(array $flows = []): void
    {
        foreach ($flows as $flow) {
            ChatFlow::query()->updateOrCreate(['slug' => $flow['slug']], $flow);
        }
    }
}
