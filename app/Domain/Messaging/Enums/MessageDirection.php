<?php

namespace App\Domain\Messaging\Enums;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Recebida',
            self::Outbound => 'Enviada',
        };
    }
}
