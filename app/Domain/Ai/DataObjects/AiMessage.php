<?php

namespace App\Domain\Ai\DataObjects;

readonly class AiMessage
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $role,
        public string $content,
        public array $extra = [],
    ) {}

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    /**
     * @param  array<int, AiToolCall>  $toolCalls
     */
    public static function assistant(string $content, array $toolCalls = []): self
    {
        return new self('assistant', $content, $toolCalls === [] ? [] : ['tool_calls' => $toolCalls]);
    }

    public static function tool(string $toolCallId, string $name, string $content): self
    {
        return new self('tool', $content, ['tool_call_id' => $toolCallId, 'name' => $name]);
    }
}
