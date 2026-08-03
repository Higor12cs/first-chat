<?php

namespace App\Actions\Conversations;

use App\Events\Conversations\ConversationUpdated;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Services\Conversations\ConversationRouter;

class ApplyConversationTags
{
    public function __construct(
        private readonly ConversationRouter $router,
        private readonly CloseConversation $closeConversation,
    ) {}

    /**
     * @param  array<int, int>  $tagIds
     */
    public function handle(Conversation $conversation, array $tagIds): Conversation
    {
        $previous = $conversation->tags()->pluck('tags.id')->all();

        $conversation->tags()->sync($tagIds);

        $added = Tag::query()->whereIn('id', array_diff($tagIds, $previous))->get();

        foreach ($added as $tag) {
            $this->runAutomation($conversation, $tag);
        }

        ConversationUpdated::dispatch($conversation->refresh());

        return $conversation;
    }

    private function runAutomation(Conversation $conversation, Tag $tag): void
    {
        $automation = $tag->automation ?? [];

        if ($queueId = data_get($automation, 'service_queue_id')) {
            $queue = ServiceQueue::find($queueId);

            if ($queue !== null) {
                $this->router->moveToQueue($conversation, $queue);
            }
        }

        if (data_get($automation, 'close_conversation')) {
            $this->closeConversation->handle($conversation, null, "Automação da tag {$tag->name}.");
        }
    }
}
