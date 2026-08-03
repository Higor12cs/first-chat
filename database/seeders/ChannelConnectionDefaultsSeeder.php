<?php

namespace Database\Seeders;

use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\ServiceQueue;
use Illuminate\Database\Seeder;

class ChannelConnectionDefaultsSeeder extends Seeder
{
    public function run(string $flowSlug = '', string $queueSlug = ''): void
    {
        $flow = ChatFlow::query()->where('slug', $flowSlug)->value('id');
        $queue = ServiceQueue::query()->where('slug', $queueSlug)->value('id');

        ChannelConnection::query()->each(function (ChannelConnection $connection) use ($flow, $queue): void {
            $connection->forceFill(array_filter([
                'chat_flow_id' => $flow,
                'default_service_queue_id' => $queue,
            ]))->save();
        });
    }
}
