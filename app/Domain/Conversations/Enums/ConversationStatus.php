<?php

namespace App\Domain\Conversations\Enums;

enum ConversationStatus: string
{
    case Bot = 'bot';
    case Ai = 'ai';
    case AfterHours = 'after_hours';
    case Pending = 'pending';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Bot => 'Chatbot',
            self::Ai => 'Atendimento IA',
            self::AfterHours => 'Fora de Hora',
            self::Pending => 'Aguardando',
            self::Open => 'Atendimento Humano',
            self::Closed => 'Encerrado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bot => 'info',
            self::Ai => 'primary',
            self::AfterHours => 'muted',
            self::Pending => 'warning',
            self::Open => 'success',
            self::Closed => 'muted',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }

    public function isAutomated(): bool
    {
        return $this === self::Bot || $this === self::Ai;
    }

    public function section(bool $isGroup = false): ConversationSection
    {
        if ($isGroup) {
            return ConversationSection::Groups;
        }

        return match ($this) {
            self::Bot, self::Ai => ConversationSection::Automatic,
            self::AfterHours => ConversationSection::AfterHours,
            self::Open => ConversationSection::Manual,
            default => ConversationSection::Waiting,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases(),
        );
    }
}
