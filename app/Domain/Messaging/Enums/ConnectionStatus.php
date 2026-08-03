<?php

namespace App\Domain\Messaging\Enums;

enum ConnectionStatus: string
{
    case Disconnected = 'disconnected';
    case Connecting = 'connecting';
    case Connected = 'connected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Disconnected => 'Desconectado',
            self::Connecting => 'Conectando',
            self::Connected => 'Conectado',
            self::Failed => 'Falhou',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::Connecting => 'warning',
            self::Failed => 'danger',
            self::Disconnected => 'muted',
        };
    }
}
