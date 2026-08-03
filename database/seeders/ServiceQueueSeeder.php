<?php

namespace Database\Seeders;

use App\Models\ServiceQueue;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceQueueSeeder extends Seeder
{
    /**
     * @param  array<int, array<string, mixed>>  $queues
     */
    public function run(array $queues = []): void
    {
        $businessHours = collect(range(1, 5))
            ->mapWithKeys(fn (int $day): array => [(string) $day => ['start' => '08:00', 'end' => '18:00']])
            ->all();

        $owner = User::query()->orderBy('id')->first();

        foreach ($queues as $queue) {
            $model = ServiceQueue::query()->updateOrCreate(
                ['slug' => $queue['slug']],
                [...$queue, 'business_hours' => $queue['business_hours'] ?? $businessHours, 'is_active' => true],
            );

            if ($owner !== null) {
                $model->users()->syncWithoutDetaching([$owner->id]);
            }
        }
    }
}
