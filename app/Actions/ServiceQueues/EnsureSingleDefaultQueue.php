<?php

namespace App\Actions\ServiceQueues;

use App\Models\ServiceQueue;

class EnsureSingleDefaultQueue
{
    public function handle(?ServiceQueue $preferred = null): ?ServiceQueue
    {
        $default = $preferred?->is_default === true ? $preferred : null;

        $default ??= ServiceQueue::query()->where('is_default', true)->ordered()->first()
            ?? ServiceQueue::query()->ordered()->first();

        if ($default === null) {
            return null;
        }

        ServiceQueue::query()
            ->whereKeyNot($default->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $default->is_default) {
            $default->forceFill(['is_default' => true])->save();
        }

        return $default;
    }
}
