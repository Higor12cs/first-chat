<?php

namespace App\Domain\Chatbot\DataObjects;

use App\Models\Conversation;
use App\Models\Message;

readonly class FlowContext
{
    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public Conversation $conversation,
        public array $node,
        public array $state,
        public ?Message $incoming = null,
    ) {}

    public function data(string $key, mixed $default = null): mixed
    {
        return data_get($this->node, "data.{$key}", $default);
    }

    public function answer(): string
    {
        return trim((string) ($this->incoming?->body ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        return data_get($this->state, 'answers', []);
    }
}
