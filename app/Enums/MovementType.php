<?php

namespace App\Enums;

enum MovementType: string
{
    case Intake = 'intake';
    case Hold = 'hold';
    case Quarantine = 'quarantine';
    case Foster = 'foster';
    case TrialAdoption = 'trial_adoption';
    case Adoption = 'adoption';
    case Reclaim = 'reclaim';
    case Transfer = 'transfer';
    case Deceased = 'deceased';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Intake',
            self::Hold => 'Hold',
            self::Quarantine => 'Quarantine',
            self::Foster => 'Foster',
            self::TrialAdoption => 'Trial adoption',
            self::Adoption => 'Adoption',
            self::Reclaim => 'Reclaim',
            self::Transfer => 'Transfer',
            self::Deceased => 'Deceased',
        };
    }

    /**
     * Movements after which the animal is no longer in the shelter's care.
     */
    public function isDeparture(): bool
    {
        return match ($this) {
            self::Adoption, self::Reclaim, self::Transfer, self::Deceased => true,
            self::Intake, self::Hold, self::Quarantine, self::Foster, self::TrialAdoption => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function departureValues(): array
    {
        return array_values(array_map(
            fn (self $type): string => $type->value,
            array_filter(self::cases(), fn (self $type): bool => $type->isDeparture()),
        ));
    }

    public function typicallyNeedsPerson(): bool
    {
        return match ($this) {
            self::Foster, self::TrialAdoption, self::Adoption, self::Reclaim, self::Transfer => true,
            self::Intake, self::Hold, self::Quarantine, self::Deceased => false,
        };
    }
}
