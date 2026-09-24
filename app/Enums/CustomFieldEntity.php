<?php

namespace App\Enums;

enum CustomFieldEntity: string
{
    case Animal = 'animal';

    public function label(): string
    {
        return match ($this) {
            self::Animal => 'Animal',
        };
    }
}
