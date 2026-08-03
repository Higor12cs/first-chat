<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * @param  array<int, array<string, mixed>>  $tags
     */
    public function run(array $tags = []): void
    {
        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(['slug' => $tag['slug']], [
                ...$tag,
                'automation' => $tag['automation'] ?? null,
            ]);
        }
    }
}
