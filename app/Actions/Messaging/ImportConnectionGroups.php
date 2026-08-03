<?php

namespace App\Actions\Messaging;

use App\Domain\Messaging\Contracts\ListsGroups;
use App\Domain\Messaging\Exceptions\ConnectorException;
use App\Models\ChannelConnection;
use App\Services\Conversations\ContactResolver;
use App\Services\Conversations\ConversationRouter;
use App\Services\Messaging\ConnectorManager;
use Illuminate\Support\Facades\Log;

class ImportConnectionGroups
{
    public function __construct(
        private readonly ConnectorManager $connectors,
        private readonly ContactResolver $contacts,
        private readonly ConversationRouter $router,
    ) {}

    public function handle(ChannelConnection $connection): int
    {
        $connector = $this->connectors->for($connection);

        if (! $connector instanceof ListsGroups) {
            return 0;
        }

        try {
            $groups = $connector->listGroups();
        } catch (ConnectorException $exception) {
            Log::warning('Could not list the channel groups.', [
                'connection_id' => $connection->id,
                'tenant_id' => $connection->tenant_id,
                'driver' => $connection->driver,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }

        foreach ($groups as $identity) {
            $contactChannel = $this->contacts->resolve($connection, $identity);

            $this->router->resolveOpenConversation($connection, $contactChannel, inbound: false);
        }

        return count($groups);
    }
}
