<?php

namespace App\Domain\Messaging\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Sent => 'Enviada',
            self::Delivered => 'Entregue',
            self::Read => 'Lida',
            self::Failed => 'Falhou',
            self::Canceled => 'Cancelada',
            self::Deleted => 'Excluída',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Canceled => -2,
            self::Failed => -1,
            self::Pending => 0,
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            self::Deleted => 4,
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Failed;
    }

    public function isAfter(self $status): bool
    {
        return $this->rank() > $status->rank();
    }
}
