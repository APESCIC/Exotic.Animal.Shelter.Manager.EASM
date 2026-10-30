<?php

namespace App\Enums;

use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder;

enum AnimalListFilter: string
{
    case Quarantine = 'quarantine';
    case MedicalOverdue = 'medical_overdue';
    case Cites = 'cites';
    case MissingArticleTen = 'missing_article_10';
    case Dwa = 'dwa';

    public function label(): string
    {
        return match ($this) {
            self::Quarantine => 'In quarantine',
            self::MedicalOverdue => 'Overdue medical',
            self::Cites => 'CITES-listed in care',
            self::MissingArticleTen => 'Annex A without Article 10',
            self::Dwa => 'DWA-licensed in care',
        };
    }

    /**
     * @param  Builder<Animal>  $query
     * @return Builder<Animal>
     */
    public function apply(Builder $query): Builder
    {
        return match ($this) {
            self::Quarantine => $query->inQuarantine(),
            self::MedicalOverdue => $query->withOverdueMedical(),
            self::Cites => $query->citesListed(),
            self::MissingArticleTen => $query->missingArticleTen(),
            self::Dwa => $query->dwaListed(),
        };
    }
}
