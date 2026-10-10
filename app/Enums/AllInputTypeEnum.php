<?php

namespace App\Enums;

enum AllInputTypeEnum: string
{
    case Production = 'production';
    case Return = 'return';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
