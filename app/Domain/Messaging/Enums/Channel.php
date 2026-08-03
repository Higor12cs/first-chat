<?php

namespace App\Domain\Messaging\Enums;

enum Channel: string
{
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WhatsApp => 'success',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $channel): array => [
                'value' => $channel->value,
                'label' => $channel->label(),
                'color' => $channel->color(),
            ],
            self::cases(),
        );
    }
}
