<?php

namespace App;

enum AdminAccessEnum: string
{
    case ACTIVE = 'active';
    case FREE_TRIAL = 'free_trial';
    case BLOCKED = 'blocked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
