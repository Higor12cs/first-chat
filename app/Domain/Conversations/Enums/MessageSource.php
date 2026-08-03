<?php

namespace App\Domain\Conversations\Enums;

enum MessageSource: string
{
    case Contact = 'contact';
    case Agent = 'agent';
    case Ai = 'ai';
    case Bot = 'bot';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Contact => 'Contato',
            self::Agent => 'Atendente',
            self::Ai => 'Inteligência Artificial',
            self::Bot => 'Chatbot',
            self::System => 'Sistema',
        };
    }
}
