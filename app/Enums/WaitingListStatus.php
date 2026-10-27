<?php

namespace App\Enums;

enum WaitingListStatus: string
{
    case Open = 'open';
    case Placed = 'placed';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Waiting',
            self::Placed => 'Placed',
            self::Removed => 'Removed',
        };
    }
}
