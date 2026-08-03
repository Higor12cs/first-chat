<?php

namespace App\Domain\Conversations\Enums;

use Illuminate\Database\Eloquent\Builder;

enum ConversationSection: string
{
    case Automatic = 'automatic';
    case AfterHours = 'after_hours';
    case Waiting = 'waiting';
    case Manual = 'manual';
    case Groups = 'groups';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automático',
            self::AfterHours => 'Fora de Hora',
            self::Waiting => 'Aguardando',
            self::Manual => 'Manual',
            self::Groups => 'Grupos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Automatic => 'Atendidos pelo chatbot ou pela IA.',
            self::AfterHours => 'Chegaram fora do horário de atendimento da empresa.',
            self::Waiting => 'Transferidos para um setor e aguardando alguém assumir.',
            self::Manual => 'Já atribuídos a um atendente.',
            self::Groups => 'Conversas em grupo.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Automatic => 'info',
            self::AfterHours => 'muted',
            self::Waiting => 'warning',
            self::Manual => 'success',
            self::Groups => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Automatic => 'bot',
            self::AfterHours => 'moon',
            self::Waiting => 'clock',
            self::Manual => 'headset',
            self::Groups => 'users',
        };
    }

    public function acceptsTransfer(): bool
    {
        return in_array($this, [self::Automatic, self::Waiting, self::Manual], true);
    }

    public function requiresQueue(): bool
    {
        return in_array($this, [self::Waiting, self::Manual], true);
    }

    public function requiresAssignee(): bool
    {
        return $this === self::Manual;
    }

    public function requiresChatFlow(): bool
    {
        return $this === self::Automatic;
    }

    /**
     * @return array<int, string>
     */
    public static function transferValues(): array
    {
        return array_map(
            fn (self $section): string => $section->value,
            array_filter(self::cases(), fn (self $section): bool => $section->acceptsTransfer()),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, color: string, icon: string}>
     */
    public static function transferOptions(): array
    {
        return array_values(array_map(
            fn (self $section): array => [
                'value' => $section->value,
                'label' => $section->label(),
                'description' => $section->transferDescription(),
                'color' => $section->color(),
                'icon' => $section->icon(),
            ],
            array_filter(self::cases(), fn (self $section): bool => $section->acceptsTransfer()),
        ));
    }

    private function transferDescription(): string
    {
        return match ($this) {
            self::Automatic => 'Devolve o atendimento ao chatbot a partir do nível escolhido.',
            self::Waiting => 'Deixa o atendimento no setor até alguém assumir.',
            self::Manual => 'Entrega o atendimento a um usuário do setor.',
            default => $this->description(),
        };
    }

    /**
     * @return array<int, ConversationStatus>
     */
    public function statuses(): array
    {
        return match ($this) {
            self::Automatic => [ConversationStatus::Bot, ConversationStatus::Ai],
            self::AfterHours => [ConversationStatus::AfterHours],
            self::Waiting => [ConversationStatus::Pending],
            self::Manual => [ConversationStatus::Open],
            self::Groups => array_filter(
                ConversationStatus::cases(),
                fn (ConversationStatus $status): bool => $status->isActive(),
            ),
        };
    }

    public function apply(Builder $query): Builder
    {
        $statuses = array_map(fn (ConversationStatus $status): string => $status->value, $this->statuses());

        return $query
            ->where('is_group', $this === self::Groups)
            ->whereIn('status', $statuses);
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, color: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $section): array => [
                'value' => $section->value,
                'label' => $section->label(),
                'description' => $section->description(),
                'color' => $section->color(),
                'icon' => $section->icon(),
            ],
            self::cases(),
        );
    }
}
