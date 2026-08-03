<?php

namespace App\Actions\Cards;

use App\Actions\Messaging\SendMessage;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\Card;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\Messaging\MessageVariables;

class SendCard
{
    public function __construct(
        private readonly SendMessage $sendMessage,
        private readonly MessageVariables $variables,
    ) {}

    public function handle(
        Conversation $conversation,
        ?Card $card,
        MessageSource $source = MessageSource::System,
    ): ?Message {
        if ($card === null || ! $card->is_active || blank($card->body)) {
            return null;
        }

        return $this->sendMessage->handle(
            conversation: $conversation,
            body: $this->variables->apply($card->body, $conversation),
            source: $source,
        );
    }
}
