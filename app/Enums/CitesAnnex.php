<?php

namespace App\Enums;

enum CitesAnnex: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';
    case NotListed = 'not_listed';

    public function label(): string
    {
        return match ($this) {
            self::A => 'Annex A',
            self::B => 'Annex B',
            self::C => 'Annex C',
            self::D => 'Annex D',
            self::NotListed => 'Not listed',
        };
    }

    public function isListed(): bool
    {
        return match ($this) {
            self::A, self::B, self::C, self::D => true,
            self::NotListed => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function listedValues(): array
    {
        return array_values(array_map(
            fn (self $annex): string => $annex->value,
            array_filter(self::cases(), fn (self $annex): bool => $annex->isListed()),
        ));
    }
}
