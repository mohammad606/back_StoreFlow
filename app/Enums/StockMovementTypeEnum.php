<?php

namespace App\Enums;

enum StockMovementTypeEnum: string
{
    case Initial = 'initial';
    case ManualUpdate = 'manual_update';
    case Input = 'input';
    case Order = 'order';
    case InventoryAdjustment = 'inventory_adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
