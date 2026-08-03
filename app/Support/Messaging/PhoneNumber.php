<?php

namespace App\Support\Messaging;

class PhoneNumber
{
    /**
     * @return array<int, string>
     */
    public static function variants(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '' || ! str_starts_with($digits, '55')) {
            return $digits === '' ? [] : [$digits];
        }

        $national = substr($digits, 2);

        return array_values(array_unique([$digits, ...self::counterpart($national)]));
    }

    /**
     * @return array<int, string>
     */
    private static function counterpart(string $national): array
    {
        $area = substr($national, 0, 2);
        $subscriber = substr($national, 2);

        if (strlen($subscriber) === 9 && $subscriber[0] === '9') {
            return ['55'.$area.substr($subscriber, 1)];
        }

        if (strlen($subscriber) === 8 && $subscriber[0] >= '6') {
            return ['55'.$area.'9'.$subscriber];
        }

        return [];
    }
}
