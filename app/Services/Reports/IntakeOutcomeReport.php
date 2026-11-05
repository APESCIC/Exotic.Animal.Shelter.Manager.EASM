<?php

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Support\Csv;
use Carbon\CarbonImmutable;

class IntakeOutcomeReport
{
    public function __construct(private readonly CareStays $stays) {}

    /**
     * @return list<MovementType>
     */
    public static function outcomeTypes(): array
    {
        return array_values(array_filter(MovementType::cases(), fn (MovementType $type): bool => $type->isDeparture()));
    }

    /**
     * @return array{
     *     in_care_at_start: int,
     *     in_care_at_end: int,
     *     intakes: int,
     *     outcomes: array<string, int>,
     *     outcomes_total: int,
     *     species: array<string, array{intakes: int, outcomes: array<string, int>}>
     * }
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $outcomes = array_fill_keys(array_map(fn (MovementType $type): string => $type->value, self::outcomeTypes()), 0);
        $summary = [
            'in_care_at_start' => count($this->stays->on($from)),
            'in_care_at_end' => count($this->stays->on($to)),
            'intakes' => 0,
            'outcomes' => $outcomes,
            'outcomes_total' => 0,
            'species' => [],
        ];

        foreach ($this->stays->all() as $stay) {
            $species = $stay->animal->species ?: 'Not recorded';
            $summary['species'][$species] ??= ['intakes' => 0, 'outcomes' => $outcomes];

            if ($stay->from->betweenIncluded($from, $to)) {
                $summary['intakes']++;
                $summary['species'][$species]['intakes']++;
            }

            if ($stay->to !== null && $stay->outcome !== null && $stay->to->betweenIncluded($from, $to)) {
                $summary['outcomes'][$stay->outcome->value]++;
                $summary['outcomes_total']++;
                $summary['species'][$species]['outcomes'][$stay->outcome->value]++;
            }
        }

        $summary['species'] = array_filter(
            $summary['species'],
            fn (array $row): bool => $row['intakes'] > 0 || array_sum($row['outcomes']) > 0,
        );
        ksort($summary['species'], SORT_NATURAL | SORT_FLAG_CASE);

        return $summary;
    }

    /**
     * @param  array{species: array<string, array{intakes: int, outcomes: array<string, int>}>}  $summary
     */
    public function csv(array $summary): string
    {
        $header = ['Species', 'Intakes', ...array_map(fn (MovementType $type): string => $type->label(), self::outcomeTypes()), 'Outcomes total'];

        return Csv::make($header, array_map(
            fn (string $species, array $row): array => [$species, $row['intakes'], ...array_values($row['outcomes']), array_sum($row['outcomes'])],
            array_keys($summary['species']),
            $summary['species'],
        ));
    }
}
