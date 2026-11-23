<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Enclosure;
use Illuminate\Support\Collection;

class DashboardShelter
{
    /**
     * @return array{
     *     on_shelter: int,
     *     quarantine: int,
     *     available: int,
     *     fostered: int,
     *     recent: Collection<int, Animal>,
     *     compact_sections: list<array{title: string, hint: ?string, url: ?string, enclosure: ?Enclosure, animals: Collection<int, Animal>}>
     * }
     */
    public function panel(ShelterView $shelterView): array
    {
        return [
            'on_shelter' => Animal::query()->inCare()->count(),
            'quarantine' => Animal::query()->inQuarantine()->count(),
            'available' => Animal::query()->inCare()->adoptable()->count(),
            'fostered' => Animal::query()
                ->inCare()
                ->whereHas('latestMovement', fn ($movement) => $movement->where('type', MovementType::Foster->value))
                ->count(),
            'recent' => Animal::query()
                ->inCare()
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'name', 'species', 'updated_at', 'primary_photo_path']),
            'compact_sections' => $shelterView->compactEnclosureSections(12),
        ];
    }
}
