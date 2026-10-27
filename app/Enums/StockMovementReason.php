<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Received = 'received';
    case Used = 'used';
    case Wasted = 'wasted';
    case Counted = 'counted';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Used => 'Used',
            self::Wasted => 'Wasted or expired',
            self::Counted => 'Stocktake count',
        };
    }

    public function quantityHint(): string
    {
        return match ($this) {
            self::Received => 'Amount delivered',
            self::Used, self::Wasted => 'Amount taken out',
            self::Counted => 'Amount actually on the shelf',
        };
    }
}
