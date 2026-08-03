<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Contracts\DeletesMessages;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Message;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RevokeMessage implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(public Message $message)
    {
        $this->onQueue('messaging');
    }

    public function handle(ConnectorManager $connectors): void
    {
        $this->forTenant($this->message->tenant, fn () => $this->revoke($connectors));
    }

    private function revoke(ConnectorManager $connectors): void
    {
        $external = $this->message->fresh()?->external_id;

        if (blank($external)) {
            return;
        }

        $connection = $this->message->conversation->connection;

        if ($connection === null) {
            return;
        }

        if ($connection->status !== ConnectionStatus::Connected) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        $connector = $connectors->for($connection);

        if ($connector instanceof DeletesMessages) {
            $connector->deleteMessage($external);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->message->tenant_id];
    }
}
