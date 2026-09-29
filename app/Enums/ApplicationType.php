<?php

namespace App\Enums;

enum ApplicationType: string
{
    case Adopter = 'adopter';
    case Volunteer = 'volunteer';
    case Foster = 'foster';

    public function label(): string
    {
        return match ($this) {
            self::Adopter => 'Adopter',
            self::Volunteer => 'Volunteer',
            self::Foster => 'Foster',
        };
    }

    public function personCategory(): PersonCategory
    {
        return match ($this) {
            self::Adopter => PersonCategory::Adopter,
            self::Volunteer => PersonCategory::Volunteer,
            self::Foster => PersonCategory::Foster,
        };
    }
}
