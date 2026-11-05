<?php

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Movement;
use Carbon\CarbonImmutable;

/**
 * Builds care stays from movement history. An animal with no movements is in care
 * from the day its record was created.
 */
class CareStays
{
    /**
     * @var list<CareStay>|null
     */
    private ?array $stays = null;

    /**
     * @return list<CareStay>
     */
    public function all(): array
    {
        if ($this->stays !== null) {
            return $this->stays;
        }

        $animals = Animal::query()
            ->where('non_shelter', false)
            ->with(['movements', 'enclosure'])
            ->orderBy('name')
            ->get();

        $stays = [];

        foreach ($animals as $animal) {
            array_push($stays, ...$this->forAnimal($animal));
        }

        usort($stays, fn (CareStay $a, CareStay $b): int => [$a->from, $a->animal->name] <=> [$b->from, $b->animal->name]);

        return $this->stays = $stays;
    }

    /**
     * @return list<CareStay>
     */
    public function on(CarbonImmutable $day): array
    {
        return array_values(array_filter($this->all(), fn (CareStay $stay): bool => $stay->covers($day)));
    }

    /**
     * @return list<CareStay>
     */
    public function overlapping(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values(array_filter($this->all(), fn (CareStay $stay): bool => $stay->overlaps($from, $to)));
    }

    /**
     * @return list<CareStay>
     */
    public function forAnimal(Animal $animal): array
    {
        $created = CarbonImmutable::parse($animal->created_at ?? 'today')->startOfDay();
        $movements = $animal->movements->sortBy([['moved_at', 'asc'], ['id', 'asc']]);
        $stays = [];
        $start = $movements->isEmpty() ? $created : null;

        /** @var Movement $movement */
        foreach ($movements as $movement) {
            $day = CarbonImmutable::parse($movement->moved_at)->startOfDay();

            if ($movement->type->isDeparture()) {
                if ($start === null && $stays === [] && $created->lte($day)) {
                    $start = $created;
                }

                if ($start !== null) {
                    $stays[] = new CareStay($animal, $start, $day, $movement->type);
                    $start = null;
                }

                continue;
            }

            $start ??= $day;
        }

        if ($start !== null) {
            $deceased = $animal->deceased_at !== null ? CarbonImmutable::parse($animal->deceased_at)->startOfDay() : null;
            $stays[] = new CareStay($animal, $start, $deceased, $deceased !== null ? MovementType::Deceased : null);
        }

        return $stays;
    }
}
