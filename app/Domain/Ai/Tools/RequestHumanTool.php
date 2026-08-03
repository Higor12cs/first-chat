<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Events\Ai\AiHandoffRequested;
use App\Models\Conversation;

class RequestHumanTool implements AiTool
{
    public function name(): string
    {
        return 'request_human';
    }

    public function label(): string
    {
        return 'Encaminhar para Atendente';
    }

    public function description(): string
    {
        return 'Encaminha o atendimento para um atendente humano quando o cliente pedir ou o assunto exigir.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string', 'description' => 'Motivo do encaminhamento.'],
            ],
            'required' => ['reason'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        if ($conversation->aiObjective !== null) {
            AiHandoffRequested::dispatch(
                $conversation,
                $conversation->aiObjective,
                $arguments['reason'] ?? null,
                announcedByAi: true,
            );
        }

        return 'Atendimento encaminhado para um atendente humano.';
    }
}
