<?php

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Models\Animal;
use Carbon\CarbonImmutable;

/**
 * One continuous period an animal was in the shelter's care. The animal is in care
 * from the arrival date and out of care from the departure date.
 */
final class CareStay
{
    public function __construct(
        public readonly Animal $animal,
        public readonly CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly ?MovementType $outcome,
    ) {}

    public function covers(CarbonImmutable $day): bool
    {
        return $this->from->lte($day) && ($this->to === null || $this->to->gt($day));
    }

    public function overlaps(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->from->lte($to) && ($this->to === null || $this->to->gt($from));
    }

    public function daysInCare(CarbonImmutable $asOf): int
    {
        $end = $this->to !== null && $this->to->lt($asOf) ? $this->to : $asOf;

        return max(0, (int) $this->from->diffInDays($end));
    }

    public function outcomeLabel(): string
    {
        return $this->outcome?->label() ?? ($this->to === null ? 'In care' : 'Left care');
    }
}
