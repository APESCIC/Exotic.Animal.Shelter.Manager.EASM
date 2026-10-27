<?php

namespace App\Enums;

enum DocumentTemplateType: string
{
    case AdoptionContract = 'adoption_contract';
    case Invoice = 'invoice';
    case EnclosureCard = 'enclosure_card';

    public function label(): string
    {
        return match ($this) {
            self::AdoptionContract => 'Adoption contract',
            self::Invoice => 'Invoice',
            self::EnclosureCard => 'Enclosure card',
        };
    }

    public function needsSignature(): bool
    {
        return match ($this) {
            self::AdoptionContract => true,
            self::Invoice, self::EnclosureCard => false,
        };
    }
}
