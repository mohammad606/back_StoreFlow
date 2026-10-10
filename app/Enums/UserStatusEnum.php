<?php

namespace App\Enums;

enum UserStatusEnum: string
{
    case Active = 'active';
    case FreeTrial = 'free_trial';
    case Blocked = 'blocked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
