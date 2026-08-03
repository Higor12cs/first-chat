<?php

namespace App\Jobs\Messaging;

use App\Actions\Messaging\ImportConnectionGroups;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncConnectionGroups implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 1;

    public function __construct(public ChannelConnection $channelConnection)
    {
        $this->onQueue('maintenance');
    }

    public function handle(ImportConnectionGroups $import): void
    {
        $this->forTenant($this->channelConnection->tenant, fn () => $import->handle($this->channelConnection));
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->channelConnection->tenant_id];
    }
}
