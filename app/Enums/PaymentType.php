<?php

namespace App\Enums;

enum PaymentType: string
{
    case Donation = 'donation';
    case AdoptionFee = 'adoption_fee';
    case Sponsorship = 'sponsorship';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Donation => 'Donation',
            self::AdoptionFee => 'Adoption fee',
            self::Sponsorship => 'Sponsorship',
            self::Other => 'Other',
        };
    }

    /**
     * Fees buy a service, so HMRC does not treat them as gifts.
     */
    public function canClaimGiftAid(): bool
    {
        return match ($this) {
            self::Donation, self::Sponsorship => true,
            self::AdoptionFee, self::Other => false,
        };
    }
}
