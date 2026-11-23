<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Lookup = 'lookup';
    case MultiLookup = 'multi_lookup';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Boolean => 'Yes / no',
            self::Lookup => 'Lookup',
            self::MultiLookup => 'Multi-lookup',
        };
    }

    public function usesLookupList(): bool
    {
        return match ($this) {
            self::Lookup, self::MultiLookup => true,
            default => false,
        };
    }
}
