<?php

namespace App\Jobs\Messaging;

use App\Domain\Messaging\DataObjects\MessageResult;
use App\Domain\Messaging\DataObjects\OutgoingMessage;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Events\Conversations\MessageStatusUpdated;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Message;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverMessage implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 60];

    public function __construct(public Message $message)
    {
        $this->onQueue('messaging');
    }

    public function handle(ConnectorManager $connectors): void
    {
        $this->forTenant($this->message->tenant, fn () => $this->deliver($connectors));
    }

    private function deliver(ConnectorManager $connectors): void
    {
        if ($this->wasCalledOff()) {
            return;
        }

        $conversation = $this->message->conversation()->with(['connection', 'contactChannel'])->firstOrFail();
        $connector = $connectors->for($conversation->connection);

        $result = $connector->send(new OutgoingMessage(
            recipient: $conversation->contactChannel->identifier,
            type: $this->message->type,
            body: $this->message->body,
            mediaUrl: $this->message->mediaUrlForProvider(),
            mediaName: $this->message->media_name,
            mediaMimeType: $this->message->media_mime_type,
            replyToExternalId: $this->message->replyTo?->external_id,
            buttons: data_get($this->message->metadata, 'buttons', []),
        ));

        if ($this->shouldRetry($result)) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        if ($this->wasCalledOff()) {
            if (filled($result->externalId)) {
                $this->message->forceFill(['external_id' => $result->externalId])->save();

                RevokeMessage::dispatch($this->message);
            }

            return;
        }

        $this->message->forceFill([
            'external_id' => $result->externalId ?? $this->message->external_id,
            'status' => $result->status,
            'error' => $result->error,
        ])->save();

        MessageStatusUpdated::dispatch($this->message);
    }

    private function shouldRetry(MessageResult $result): bool
    {
        return ! $result->successful()
            && $result->retryable
            && isset($this->job)
            && $this->attempts() < $this->tries;
    }

    private function wasCalledOff(): bool
    {
        return in_array(
            $this->message->fresh()?->status,
            [MessageStatus::Canceled, MessageStatus::Deleted],
            true,
        );
    }

    public function failed(Throwable $exception): void
    {
        if ($this->wasCalledOff()) {
            return;
        }

        $this->message->forceFill([
            'status' => MessageStatus::Failed,
            'error' => $exception->getMessage(),
        ])->save();

        MessageStatusUpdated::dispatch($this->message);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->message->tenant_id];
    }
}
