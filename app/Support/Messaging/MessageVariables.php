<?php

namespace App\Support\Messaging;

use App\Models\Conversation;

class MessageVariables
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function apply(string $text, Conversation $conversation, array $extra = []): string
    {
        $contact = $conversation->contact;

        $values = [
            'contato.nome' => $contact?->name,
            'contato.telefone' => $contact?->phone,
            'contato.email' => $contact?->email,
            'atendimento.canal' => $conversation->channel->label(),
            ...$extra,
        ];

        foreach ($values as $key => $value) {
            $text = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], (string) $value, $text);
        }

        return $text;
    }
}
