<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\Contracts\SupportsPresence;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

class SendReadReceipt implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    private const BATCH = 50;

    public int $tries = 2;

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue('realtime');
    }

    public function handle(ConnectorManager $connectors): void
    {
        $this->forTenant($this->conversation->tenant, fn () => $this->confirm($connectors));
    }

    private function confirm(ConnectorManager $connectors): void
    {
        $connection = $this->conversation->connection;
        $channel = $this->conversation->contactChannel;

        if ($connection?->status !== ConnectionStatus::Connected || $channel === null) {
            return;
        }

        $connector = $connectors->for($connection);

        if (! $connector instanceof SupportsPresence) {
            return;
        }

        $messages = $this->pending();

        if ($messages->isEmpty()) {
            return;
        }

        $connector->markAsRead($channel->identifier, ...$messages->pluck('external_id')->all());

        Message::query()->whereKey($messages->pluck('id'))->update(['read_at' => now()]);
    }

    /**
     * @return Collection<int, Message>
     */
    private function pending(): Collection
    {
        return $this->conversation->messages()
            ->where('direction', MessageDirection::Inbound)
            ->whereNull('read_at')
            ->whereNotNull('external_id')
            ->latest('id')
            ->limit(self::BATCH)
            ->get(['id', 'external_id']);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->conversation->tenant_id];
    }
}
