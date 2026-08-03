<?php

namespace App\Jobs\Messaging;

use App\Actions\Messaging\ProvisionConnection as ProvisionConnectionAction;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionConnection implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public ChannelConnection $channelConnection)
    {
        $this->onQueue('maintenance');
    }

    public function handle(ProvisionConnectionAction $provision): void
    {
        $this->forTenant(
            $this->channelConnection->tenant,
            fn () => $provision->handle($this->channelConnection),
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->channelConnection->tenant_id];
    }
}
