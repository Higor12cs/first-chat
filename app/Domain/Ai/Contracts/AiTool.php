<?php

namespace App\Domain\Ai\Contracts;

use App\Models\Conversation;

interface AiTool
{
    public function name(): string;

    public function label(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string;
}
