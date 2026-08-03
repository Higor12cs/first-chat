<?php

namespace App\Jobs\Ai;

use App\Actions\Ai\HandleAiTurn;
use App\Events\Ai\AiHandoffRequested;
use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAiTurn implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public int $tries = 2;

    public function __construct(public Conversation $conversation)
    {
        $this->onConnection('redis-long');
        $this->onQueue('ai');
    }

    public function handle(HandleAiTurn $handleAiTurn): void
    {
        $this->forTenant(
            $this->conversation->tenant,
            fn () => $handleAiTurn->handle($this->conversation->refresh()),
        );
    }

    public function failed(Throwable $exception): void
    {
        $conversation = $this->conversation->fresh();

        if ($conversation?->aiObjective === null) {
            return;
        }

        $this->forTenant($conversation->tenant, fn () => AiHandoffRequested::dispatch(
            $conversation,
            $conversation->aiObjective,
            'A IA não conseguiu responder: '.$exception->getMessage(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->conversation->tenant_id];
    }
}
