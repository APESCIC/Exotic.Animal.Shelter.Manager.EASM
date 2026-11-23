<?php

namespace App\Services;

use App\Enums\ShelterGroup;
use App\Enums\ShelterViewMode;
use App\Models\Animal;
use App\Models\Enclosure;
use Illuminate\Support\Collection;

class ShelterView
{
    /**
     * @return array{
     *     group: ShelterGroup,
     *     viewMode: ShelterViewMode,
     *     sections: list<array{title: string, hint: ?string, url: ?string, enclosure: ?Enclosure, animals: Collection<int, Animal>}>
     * }
     */
    public function build(ShelterGroup $group, ShelterViewMode $viewMode): array
    {
        return [
            'group' => $group,
            'viewMode' => $viewMode,
            'sections' => match ($group) {
                ShelterGroup::Enclosure => $this->enclosureSections(),
                ShelterGroup::Location => $this->animalGroupSections(
                    fn (Animal $animal): string => $animal->location ?: 'Unassigned',
                ),
                ShelterGroup::Species => $this->animalGroupSections(
                    fn (Animal $animal): string => filled($animal->species) ? $animal->species : 'Unknown species',
                ),
                ShelterGroup::Status => $this->animalGroupSections(
                    fn (Animal $animal): string => $animal->currentMovementType()?->label() ?? 'No movement',
                ),
            },
        ];
    }

    /**
     * Compact enclosure-grouped sections for the home dashboard panel.
     *
     * @return list<array{title: string, hint: ?string, url: ?string, enclosure: ?Enclosure, animals: Collection<int, Animal>}>
     */
    public function compactEnclosureSections(int $animalLimit = 12): array
    {
        $sections = $this->enclosureSections();
        $remaining = $animalLimit;
        $compact = [];

        foreach ($sections as $section) {
            if ($remaining <= 0) {
                break;
            }

            $animals = $section['animals']->take($remaining);
            $remaining -= $animals->count();
            $compact[] = [
                ...$section,
                'animals' => $animals,
            ];
        }

        return $compact;
    }

    /**
     * @return list<array{title: string, hint: ?string, url: ?string, enclosure: ?Enclosure, animals: Collection<int, Animal>}>
     */
    private function enclosureSections(): array
    {
        $enclosures = Enclosure::query()
            ->with(['occupants.latestMovement'])
            ->withCount('occupants')
            ->where(fn ($query) => $query->active()->orHas('occupants'))
            ->ordered()
            ->get();

        $sections = $enclosures->map(function (Enclosure $enclosure): array {
            $free = $enclosure->freeSpaces();

            return [
                'title' => $enclosure->label(),
                'hint' => $this->enclosureHint($enclosure, $free),
                'url' => route('enclosures.show', $enclosure),
                'enclosure' => $enclosure,
                'animals' => $enclosure->occupants,
            ];
        })->all();

        $unassigned = Animal::query()
            ->inCare()
            ->whereNull('enclosure_id')
            ->with('latestMovement')
            ->orderBy('name')
            ->get();

        if ($unassigned->isNotEmpty()) {
            $sections[] = [
                'title' => 'No enclosure',
                'hint' => null,
                'url' => null,
                'enclosure' => null,
                'animals' => $unassigned,
            ];
        }

        return $sections;
    }

    /**
     * @param  callable(Animal): string  $groupKey
     * @return list<array{title: string, hint: ?string, url: ?string, enclosure: ?Enclosure, animals: Collection<int, Animal>}>
     */
    private function animalGroupSections(callable $groupKey): array
    {
        return Animal::query()
            ->inCare()
            ->with(['latestMovement', 'enclosure'])
            ->orderBy('name')
            ->get()
            ->groupBy($groupKey)
            ->sortKeysUsing(fn (string $a, string $b): int => strcasecmp($a, $b))
            ->map(fn (Collection $animals, string $title): array => [
                'title' => $title,
                'hint' => null,
                'url' => null,
                'enclosure' => null,
                'animals' => $animals->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            ])
            ->values()
            ->all();
    }

    private function enclosureHint(Enclosure $enclosure, ?int $free): string
    {
        $parts = ['Occupants: '.$enclosure->occupancyLabel()];

        if ($free !== null && $enclosure->occupantCount() === 0) {
            $parts[] = $free === 1 ? '1 free space' : $free.' free spaces';
        } elseif ($free !== null && $free > 0) {
            $parts[] = $free === 1 ? '1 free' : $free.' free';
        }

        return implode(' · ', $parts);
    }
}
