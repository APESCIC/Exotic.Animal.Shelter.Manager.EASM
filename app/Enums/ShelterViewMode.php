<?php

namespace App\Enums;

enum ShelterViewMode: string
{
    case Grid = 'grid';
    case List = 'list';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'Photo grid',
            self::List => 'List',
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Grid;
    }
}
