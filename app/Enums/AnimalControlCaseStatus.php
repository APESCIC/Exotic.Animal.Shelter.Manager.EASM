<?php

namespace App\Enums;

enum AnimalControlCaseStatus: string
{
    case Open = 'open';
    case Dispatched = 'dispatched';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Dispatched => 'Dispatched',
            self::Closed => 'Closed',
        };
    }
}
