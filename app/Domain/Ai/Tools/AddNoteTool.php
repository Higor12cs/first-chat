<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Models\Conversation;
use App\Models\ConversationNote;

class AddNoteTool implements AiTool
{
    public function name(): string
    {
        return 'add_note';
    }

    public function label(): string
    {
        return 'Registrar Nota Interna';
    }

    public function description(): string
    {
        return 'Registra uma nota interna visível apenas para a equipe.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'note' => ['type' => 'string', 'description' => 'Conteúdo da nota.'],
            ],
            'required' => ['note'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        ConversationNote::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'body' => (string) ($arguments['note'] ?? ''),
        ]);

        return 'Nota registrada.';
    }
}
