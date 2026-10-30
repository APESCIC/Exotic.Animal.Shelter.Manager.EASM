<?php

namespace App\Enums;

enum DwaInspectionOutcome: string
{
    case Satisfactory = 'satisfactory';
    case ActionRequired = 'action_required';
    case Unsatisfactory = 'unsatisfactory';

    public function label(): string
    {
        return match ($this) {
            self::Satisfactory => 'Satisfactory',
            self::ActionRequired => 'Action required',
            self::Unsatisfactory => 'Unsatisfactory',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Satisfactory => 'adoptable',
            self::ActionRequired => 'quarantine',
            self::Unsatisfactory => 'dwa',
        };
    }
}
