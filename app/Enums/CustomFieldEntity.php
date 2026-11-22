<?php

namespace App\Enums;

enum CustomFieldEntity: string
{
    case Animal = 'animal';
    case Person = 'person';

    public function label(): string
    {
        return match ($this) {
            self::Animal => 'Animal',
            self::Person => 'Person',
        };
    }
}
