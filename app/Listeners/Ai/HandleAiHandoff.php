<?php

namespace App\Listeners\Ai;

use App\Actions\Messaging\SendMessage;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Events\Ai\AiHandoffRequested;
use App\Models\Conversation;
use App\Services\Conversations\ConversationRouter;

class HandleAiHandoff
{
    public function __construct(
        private readonly ConversationRouter $router,
        private readonly SendMessage $sendMessage,
    ) {}

    public function handle(AiHandoffRequested $event): void
    {
        $conversation = $event->conversation;

        $conversation->forceFill([
            'ai_objective_id' => null,
            'status' => ConversationStatus::Pending,
        ])->save();

        $queue = $event->objective->handoffServiceQueue;

        if ($queue !== null) {
            $this->router->moveToQueue($conversation, $queue);
        }

        if ($event->announcedByAi && $this->announced($conversation)) {
            return;
        }

        $this->sendMessage->handle(
            conversation: $conversation,
            body: 'Vou te encaminhar para um de nossos atendentes. Um momento, por favor.',
            source: MessageSource::System,
        );
    }

    private function announced(Conversation $conversation): bool
    {
        return $conversation->messages()
            ->where('source', MessageSource::Ai)
            ->where('is_internal', false)
            ->whereNotNull('body')
            ->where('created_at', '>=', now()->subMinute())
            ->exists();
    }
}
