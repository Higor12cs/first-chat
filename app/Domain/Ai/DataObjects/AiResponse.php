<?php

namespace App\Domain\Ai\DataObjects;

readonly class AiResponse
{
    /**
     * @param  array<int, AiToolCall>  $toolCalls
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $content = null,
        public array $toolCalls = [],
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $finishReason = null,
        public array $raw = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
