<?php

namespace App\Services\Reports;

use App\Support\Csv;
use App\Support\UkDate;
use Carbon\CarbonImmutable;

class AnimalTimeline
{
    /**
     * @var list<string>
     */
    public const COLUMNS = ['Animal ID', 'Name', 'Species', 'Arrived', 'Left', 'Outcome', 'Days in care'];

    public function __construct(private readonly CareStays $stays) {}

    /**
     * @return list<array{stay: CareStay, offset: float, width: float}>
     */
    public function rows(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $span = max(1, (int) $from->diffInDays($to) + 1);

        return array_map(function (CareStay $stay) use ($from, $to, $span): array {
            $start = $stay->from->lt($from) ? $from : $stay->from;
            $end = $stay->to === null || $stay->to->gt($to) ? $to->addDay() : $stay->to;

            return [
                'stay' => $stay,
                'offset' => round((int) $from->diffInDays($start) / $span * 100, 2),
                'width' => round(max(1, (int) $start->diffInDays($end)) / $span * 100, 2),
            ];
        }, $this->stays->overlapping($from, $to));
    }

    /**
     * @param  list<array{stay: CareStay, offset: float, width: float}>  $rows
     */
    public function csv(array $rows, CarbonImmutable $asOf): string
    {
        return Csv::make(self::COLUMNS, array_map(fn (array $row): array => [
            $row['stay']->animal->id,
            $row['stay']->animal->name,
            $row['stay']->animal->species,
            UkDate::format($row['stay']->from),
            $row['stay']->to ? UkDate::format($row['stay']->to) : '',
            $row['stay']->outcomeLabel(),
            $row['stay']->daysInCare($asOf),
        ], $rows));
    }
}
