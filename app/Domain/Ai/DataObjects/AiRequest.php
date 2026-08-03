<?php

namespace App\Domain\Ai\DataObjects;

use App\Domain\Ai\Contracts\AiTool;

readonly class AiRequest
{
    /**
     * @param  array<int, AiMessage>  $messages
     * @param  array<int, AiTool>  $tools
     */
    public function __construct(
        public string $model,
        public string $systemPrompt,
        public array $messages,
        public float $temperature = 0.7,
        public int $maxTokens = 1024,
        public array $tools = [],
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toolSchemas(): array
    {
        return array_map(fn (AiTool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'parameters' => $tool->schema(),
        ], $this->tools);
    }
}
