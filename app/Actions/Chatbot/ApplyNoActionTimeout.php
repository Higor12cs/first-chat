<?php

namespace App\Actions\Chatbot;

use App\Actions\Conversations\CloseConversation;
use App\Actions\Conversations\TransferConversation;
use App\Actions\Messaging\SendMessage;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\Conversation;
use App\Models\ServiceQueue;

class ApplyNoActionTimeout
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly CloseConversation $closeConversation,
        private readonly TransferConversation $transferConversation,
    ) {}

    public function handle(Conversation $conversation): bool
    {
        $action = (string) data_get($conversation->flow_state, 'no_action', config('chatbot.no_action'));

        return match ($action) {
            'queue' => $this->toQueue($conversation),
            'none' => $this->clear($conversation),
            default => $this->close($conversation),
        };
    }

    private function close(Conversation $conversation): bool
    {
        $message = (string) config('chatbot.no_action_message');

        if (filled($message)) {
            $this->sendMessage->handle(
                conversation: $conversation,
                body: $message,
                source: MessageSource::Bot,
            );
        }

        $this->closeConversation->handle($conversation, null, 'Encerrado por falta de resposta.');
        $this->clear($conversation);

        return true;
    }

    private function toQueue(Conversation $conversation): bool
    {
        $queue = ServiceQueue::query()->find(data_get($conversation->flow_state, 'no_action_service_queue_id'))
            ?? ServiceQueue::default();

        if ($queue === null) {
            return $this->close($conversation);
        }

        $this->transferConversation->toWaiting($conversation, $queue, applyAutomations: true);
        $this->clear($conversation);

        return true;
    }

    private function clear(Conversation $conversation): bool
    {
        $conversation->forceFill(['no_action_at' => null])->save();

        return true;
    }
}
