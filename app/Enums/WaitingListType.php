<?php

namespace App\Enums;

enum WaitingListType: string
{
    case Surrender = 'surrender';
    case Adoption = 'adoption';

    public function label(): string
    {
        return match ($this) {
            self::Surrender => 'Wants to surrender an animal',
            self::Adoption => 'Wants to adopt',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Surrender => 'Surrender',
            self::Adoption => 'Adoption',
        };
    }
}
