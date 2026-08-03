<?php

namespace Database\Seeders;

use App\Models\QuickReply;
use Illuminate\Database\Seeder;

class QuickReplySeeder extends Seeder
{
    /**
     * @param  array<int, array<string, mixed>>  $replies
     */
    public function run(array $replies = []): void
    {
        foreach ($replies as $reply) {
            QuickReply::query()->updateOrCreate(['shortcut' => $reply['shortcut']], $reply);
        }
    }
}
