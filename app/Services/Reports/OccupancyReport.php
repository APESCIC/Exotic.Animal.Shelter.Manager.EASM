<?php

namespace App\Services\Reports;

use App\Support\Csv;
use App\Support\UkDate;
use Carbon\CarbonImmutable;

class OccupancyReport
{
    /**
     * @var list<string>
     */
    public const COLUMNS = ['Animal ID', 'Name', 'Species', 'Enclosure', 'In care since', 'Days in care'];

    public function __construct(private readonly CareStays $stays) {}

    /**
     * @return list<CareStay>
     */
    public function stays(CarbonImmutable $on): array
    {
        return $this->stays->on($on);
    }

    /**
     * @param  list<CareStay>  $stays
     * @return array<string, int>
     */
    public function bySpecies(array $stays): array
    {
        return $this->countBy($stays, fn (CareStay $stay): string => $stay->animal->species ?: 'Not recorded');
    }

    /**
     * Grouped by the animal's current enclosure: enclosure moves are not kept as history.
     *
     * @param  list<CareStay>  $stays
     * @return array<string, int>
     */
    public function byEnclosure(array $stays): array
    {
        return $this->countBy($stays, fn (CareStay $stay): string => $stay->animal->enclosure?->name ?? 'No enclosure');
    }

    /**
     * @param  list<CareStay>  $stays
     */
    public function csv(array $stays, CarbonImmutable $on): string
    {
        return Csv::make(self::COLUMNS, array_map(fn (CareStay $stay): array => [
            $stay->animal->id,
            $stay->animal->name,
            $stay->animal->species,
            $stay->animal->enclosure?->name ?? '',
            UkDate::format($stay->from),
            $stay->daysInCare($on),
        ], $stays));
    }

    /**
     * @param  list<CareStay>  $stays
     * @param  callable(CareStay): string  $key
     * @return array<string, int>
     */
    private function countBy(array $stays, callable $key): array
    {
        $counts = [];

        foreach ($stays as $stay) {
            $group = $key($stay);
            $counts[$group] = ($counts[$group] ?? 0) + 1;
        }

        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);

        return $counts;
    }
}
