<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Events\Ai\LeadQualified;
use App\Models\Conversation;

class QualifyLeadTool implements AiTool
{
    public function name(): string
    {
        return 'qualify_lead';
    }

    public function label(): string
    {
        return 'Qualificar Lead';
    }

    public function description(): string
    {
        return 'Registra os dados de qualificação do lead assim que forem confirmados pelo cliente.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Nome do lead.'],
                'email' => ['type' => 'string', 'description' => 'Email do lead.'],
                'interest' => ['type' => 'string', 'description' => 'Interesse principal.'],
                'budget' => ['type' => 'string', 'description' => 'Orçamento informado.'],
                'notes' => ['type' => 'string', 'description' => 'Outras informações relevantes.'],
            ],
            'required' => ['name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        $contact = $conversation->contact;

        $contact->forceFill([
            'name' => $arguments['name'] ?? $contact->name,
            'email' => $arguments['email'] ?? $contact->email,
            'custom_fields' => [...$contact->custom_fields ?? [], ...array_filter([
                'interest' => $arguments['interest'] ?? null,
                'budget' => $arguments['budget'] ?? null,
                'qualification_notes' => $arguments['notes'] ?? null,
            ])],
        ])->save();

        if ($conversation->aiObjective !== null) {
            LeadQualified::dispatch($conversation, $conversation->aiObjective, $arguments);
        }

        return 'Dados de qualificação registrados.';
    }
}
