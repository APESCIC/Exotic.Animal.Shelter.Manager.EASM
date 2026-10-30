<?php

namespace App\Services;

use App\Enums\CitesCertificateType;
use App\Models\Animal;
use App\Models\CitesCertificate;
use App\Support\UkDate;
use Illuminate\Database\Eloquent\Collection;

class CitesRegister
{
    /**
     * @var list<string>
     */
    public const COLUMNS = [
        'Animal ID',
        'Name',
        'Species',
        'Identifying code',
        'Annex',
        'CITES reference',
        'Certificates',
        'Article 10 on file',
        'Status',
    ];

    /**
     * @return Collection<int, Animal>
     */
    public function animals(bool $includeDeparted): Collection
    {
        return Animal::query()
            ->onCitesRegister()
            ->when(! $includeDeparted, fn ($query) => $query->inCare())
            ->with(['citesCertificates', 'latestMovement'])
            ->orderBy('species')
            ->orderBy('name')
            ->get();
    }

    public function status(Animal $animal): string
    {
        if ($animal->deceased_at !== null) {
            return 'Deceased '.UkDate::format($animal->deceased_at);
        }

        if ($animal->non_shelter) {
            return 'Non-shelter';
        }

        $movement = $animal->latestMovement;

        return $movement !== null && $movement->type->isDeparture()
            ? $movement->type->label().' '.UkDate::format($movement->moved_at)
            : 'In care';
    }

    public function articleTenStatus(Animal $animal): string
    {
        if ($animal->citesCertificates->contains('type', CitesCertificateType::Article10)) {
            return 'Yes';
        }

        return $animal->isMissingArticleTen() && $this->status($animal) === 'In care' ? 'Missing' : 'No';
    }

    /**
     * @param  Collection<int, Animal>  $animals
     */
    public function csv(Collection $animals): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::COLUMNS, escape: '');

        foreach ($animals as $animal) {
            fputcsv($handle, [
                $animal->id,
                $animal->name,
                $animal->species,
                $animal->identifying_code,
                $animal->cites_annex?->label() ?? 'Not recorded',
                $animal->cites,
                $animal->citesCertificates
                    ->map(fn (CitesCertificate $certificate): string => trim($certificate->summary().' '.($certificate->issued_on ? UkDate::format($certificate->issued_on) : '')))
                    ->implode('; '),
                $this->articleTenStatus($animal),
                $this->status($animal),
            ], escape: '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
