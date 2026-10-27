<?php

namespace App\Enums;

enum SponsorshipFrequency: string
{
    case OneOff = 'one_off';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::OneOff => 'One-off',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
