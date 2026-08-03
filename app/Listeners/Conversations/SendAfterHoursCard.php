<?php

namespace App\Listeners\Conversations;

use App\Actions\Cards\SendCard;
use App\Events\Conversations\ConversationHeldAfterHours;
use App\Services\Tenancy\BusinessHours;

class SendAfterHoursCard
{
    public function __construct(
        private readonly BusinessHours $hours,
        private readonly SendCard $sendCard,
    ) {}

    public function handle(ConversationHeldAfterHours $event): void
    {
        $this->sendCard->handle(
            $event->conversation,
            $this->hours->cardFor($event->conversation->tenant),
        );
    }
}
