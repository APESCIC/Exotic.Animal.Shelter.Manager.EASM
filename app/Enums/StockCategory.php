<?php

namespace App\Enums;

enum StockCategory: string
{
    case Food = 'food';
    case Medical = 'medical';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food',
            self::Medical => 'Medical',
            self::Other => 'Other',
        };
    }
}
