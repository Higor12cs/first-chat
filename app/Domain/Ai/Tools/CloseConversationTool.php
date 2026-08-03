<?php

namespace App\Domain\Ai\Tools;

use App\Actions\Conversations\CloseConversation;
use App\Domain\Ai\Contracts\AiTool;
use App\Models\Conversation;

class CloseConversationTool implements AiTool
{
    public function __construct(private readonly CloseConversation $closeConversation) {}

    public function name(): string
    {
        return 'close_conversation';
    }

    public function label(): string
    {
        return 'Encerrar Atendimento';
    }

    public function description(): string
    {
        return 'Encerra o atendimento quando o objetivo foi cumprido ou o cliente se despediu.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string', 'description' => 'Resumo do encerramento.'],
            ],
            'required' => ['reason'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        $this->closeConversation->handle($conversation, null, $arguments['reason'] ?? null);

        return 'Atendimento encerrado.';
    }
}
