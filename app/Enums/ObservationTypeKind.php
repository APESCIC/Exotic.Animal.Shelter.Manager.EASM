<?php

namespace App\Enums;

enum ObservationTypeKind: string
{
    case ValueList = 'value_list';
    case FreeText = 'free_text';

    public function label(): string
    {
        return match ($this) {
            self::ValueList => 'Value list',
            self::FreeText => 'Free text',
        };
    }

    public function usesLookupList(): bool
    {
        return $this === self::ValueList;
    }
}
