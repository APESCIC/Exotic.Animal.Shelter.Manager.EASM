<?php

namespace App\Enums;

enum EquipmentType: string
{
    case UvbLamp = 'uvb_lamp';
    case HeatLamp = 'heat_lamp';
    case HeatMat = 'heat_mat';
    case Thermostat = 'thermostat';
    case Lighting = 'lighting';
    case Mister = 'mister';
    case Filter = 'filter';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UvbLamp => 'UVB lamp',
            self::HeatLamp => 'Heat lamp / bulb',
            self::HeatMat => 'Heat mat',
            self::Thermostat => 'Thermostat',
            self::Lighting => 'Lighting (non-UVB)',
            self::Mister => 'Mister / fogger',
            self::Filter => 'Filter / pump',
            self::Other => 'Other',
        };
    }

    /**
     * Wording for the diary task raised when a replacement falls due.
     */
    public function reminderVerb(): string
    {
        return match ($this) {
            self::UvbLamp, self::HeatLamp, self::Lighting => 'Replace',
            self::HeatMat, self::Thermostat, self::Mister, self::Filter, self::Other => 'Service or replace',
        };
    }
}
