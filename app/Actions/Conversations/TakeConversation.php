<?php

namespace App\Actions\Conversations;

use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TakeConversation
{
    public function __construct(private readonly TransferConversation $transferConversation) {}

    public function handle(Conversation $conversation, User $user, ?ServiceQueue $queue = null): Conversation
    {
        $queue ??= $conversation->serviceQueue ?? ServiceQueue::default();

        if ($queue === null) {
            throw ValidationException::withMessages([
                'service_queue_id' => 'Cadastre um setor antes de assumir atendimentos.',
            ]);
        }

        return $this->transferConversation->toManual($conversation, $queue, $user, $user);
    }
}
