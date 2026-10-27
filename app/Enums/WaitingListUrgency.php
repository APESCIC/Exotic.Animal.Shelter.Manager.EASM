<?php

namespace App\Enums;

enum WaitingListUrgency: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent',
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
        };
    }

    /**
     * Lower ranks are listed first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 1,
            self::High => 2,
            self::Normal => 3,
            self::Low => 4,
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Urgent => 'dwa',
            self::High => 'quarantine',
            self::Normal => 'cites',
            self::Low => 'neutral',
        };
    }
}
