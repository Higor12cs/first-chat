<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Contracts\SupportsPresence;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Conversation;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTypingIndicator implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 1;

    public function __construct(
        public Conversation $conversation,
        public bool $typing = true,
    ) {
        $this->onQueue('realtime');
    }

    public function handle(ConnectorManager $connectors): void
    {
        $this->forTenant($this->conversation->tenant, fn () => $this->announce($connectors));
    }

    private function announce(ConnectorManager $connectors): void
    {
        $connection = $this->conversation->connection;
        $channel = $this->conversation->contactChannel;

        if ($connection?->status !== ConnectionStatus::Connected || $channel === null) {
            return;
        }

        $connector = $connectors->for($connection);

        if ($connector instanceof SupportsPresence) {
            $connector->sendTyping($channel->identifier, $this->typing);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->conversation->tenant_id];
    }
}
