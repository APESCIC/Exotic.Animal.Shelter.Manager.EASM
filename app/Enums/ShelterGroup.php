<?php

namespace App\Enums;

enum ShelterGroup: string
{
    case Location = 'location';
    case Enclosure = 'enclosure';
    case Species = 'species';
    case Status = 'status';

    public function label(): string
    {
        return match ($this) {
            self::Location => 'Location',
            self::Enclosure => 'Enclosure',
            self::Species => 'Species',
            self::Status => 'Status',
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Location;
    }
}
