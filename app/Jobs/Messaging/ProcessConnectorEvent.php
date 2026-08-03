<?php

namespace App\Jobs\Messaging;

use App\Actions\Messaging\ReceiveInboundMessage;
use App\Actions\Messaging\UpdateMessageStatus;
use App\Domain\Messaging\Contracts\ConnectorEvent;
use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;
use App\Domain\Messaging\DataObjects\DeliveryStatusUpdate;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\ChannelConnection;
use App\Services\Messaging\ConnectionStatusSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessConnectorEvent implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public function __construct(
        public ChannelConnection $channelConnection,
        public ConnectorEvent $event,
    ) {
        $this->onQueue('messaging');
    }

    public function handle(
        ReceiveInboundMessage $receiveInboundMessage,
        UpdateMessageStatus $updateMessageStatus,
        ConnectionStatusSynchronizer $statusSynchronizer,
    ): void {
        $this->forTenant($this->channelConnection->tenant, function () use ($receiveInboundMessage, $updateMessageStatus, $statusSynchronizer): void {
            match (true) {
                $this->event instanceof InboundMessage => $receiveInboundMessage->handle($this->channelConnection, $this->event),
                $this->event instanceof DeliveryStatusUpdate => $updateMessageStatus->handle($this->channelConnection, $this->event),
                $this->event instanceof ConnectionStatusUpdate => $statusSynchronizer->apply($this->channelConnection, $this->event),
                default => null,
            };
        });
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->channelConnection->tenant_id];
    }
}
