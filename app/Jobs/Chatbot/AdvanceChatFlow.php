<?php

namespace App\Jobs\Chatbot;

use App\Jobs\Concerns\InteractsWithTenant;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chatbot\FlowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AdvanceChatFlow implements ShouldQueue
{
    use InteractsWithTenant, Queueable;

    public function __construct(
        public Conversation $conversation,
        public ?Message $incoming = null,
    ) {
        $this->onQueue('messaging');
    }

    public function handle(FlowEngine $engine): void
    {
        $this->forTenant(
            $this->conversation->tenant,
            fn () => $engine->advance($this->conversation->refresh(), $this->incoming),
        );
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['tenant:'.$this->conversation->tenant_id];
    }
}
