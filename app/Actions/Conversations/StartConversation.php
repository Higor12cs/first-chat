<?php

namespace App\Actions\Conversations;

use App\Domain\Messaging\DataObjects\ContactIdentity;
use App\Events\Conversations\ConversationUpdated;
use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversations\ContactResolver;
use App\Services\Conversations\ConversationRouter;

class StartConversation
{
    public function __construct(
        private readonly ContactResolver $contacts,
        private readonly ConversationRouter $router,
    ) {}

    public function handle(ChannelConnection $connection, string $phone, ?string $name = null, ?User $user = null): Conversation
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        $contactChannel = $this->contacts->resolve($connection, new ContactIdentity(
            identifier: "{$digits}@s.whatsapp.net",
            name: $name,
            phone: $digits,
        ));

        $conversation = $this->router->resolveOpenConversation($connection, $contactChannel, inbound: false);

        if ($user !== null && $conversation->assigned_user_id === null) {
            $conversation = $this->router->assign($conversation, $user);
        }

        ConversationUpdated::dispatch($conversation);

        return $conversation;
    }
}
