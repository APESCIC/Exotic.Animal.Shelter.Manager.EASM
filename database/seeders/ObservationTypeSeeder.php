<?php

namespace Database\Seeders;

use App\Enums\ObservationTypeKind;
use App\Models\LookupList;
use App\Models\LookupValue;
use App\Models\ObservationType;
use Illuminate\Database\Seeder;

class ObservationTypeSeeder extends Seeder
{
    /**
     * Starter daily observation types for reptiles and birds. Safe to re-run.
     */
    public function run(): void
    {
        $yesNoPartial = $this->ensureList(
            'observation_yes_no_partial',
            'Observation: Yes / no / partial',
            ['Yes', 'No', 'Partial'],
        );

        $reptileSpecies = [
            'Bearded dragon (demo)',
            'Corn snake (demo)',
            'Sulcata tortoise (demo)',
            'Royal python (demo)',
            'Leopard gecko (demo)',
        ];

        $birdSpecies = [
            'African grey parrot (demo)',
            'Eclectus parrot (demo)',
            'Budgerigar (demo)',
        ];

        $this->ensureType('Eaten', ObservationTypeKind::ValueList, $yesNoPartial->id, 10, $reptileSpecies);
        $this->ensureType('Drunk', ObservationTypeKind::ValueList, $yesNoPartial->id, 20, $reptileSpecies);
        $this->ensureType('Faeces', ObservationTypeKind::ValueList, $this->ensureList(
            'observation_faeces',
            'Observation: Faeces',
            ['Normal', 'Soft', 'None seen', 'Not checked'],
        )->id, 30, $reptileSpecies);
        $this->ensureType('Shedding', ObservationTypeKind::ValueList, $yesNoPartial->id, 40, $reptileSpecies);
        $this->ensureType('Basking', ObservationTypeKind::ValueList, $yesNoPartial->id, 50, $reptileSpecies);

        $this->ensureType('Eaten', ObservationTypeKind::ValueList, $yesNoPartial->id, 10, $birdSpecies);
        $this->ensureType('Drunk', ObservationTypeKind::ValueList, $yesNoPartial->id, 20, $birdSpecies);
        $this->ensureType('Droppings', ObservationTypeKind::ValueList, $this->ensureList(
            'observation_droppings',
            'Observation: Droppings',
            ['Normal', 'Soft', 'None seen', 'Not checked'],
        )->id, 30, $birdSpecies);
        $this->ensureType('Vocalisation', ObservationTypeKind::FreeText, null, 40, $birdSpecies);
    }

    /**
     * @param  list<string>  $values
     */
    private function ensureList(string $key, string $name, array $values): LookupList
    {
        $list = LookupList::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => 'Daily observation values.'],
        );

        foreach ($values as $index => $valueName) {
            LookupValue::query()->firstOrCreate(
                [
                    'lookup_list_id' => $list->id,
                    'name' => $valueName,
                ],
                [
                    'sort_order' => ($index + 1) * 10,
                    'active' => true,
                ],
            );
        }

        return $list;
    }

    /**
     * @param  list<string>|null  $speciesFilter
     */
    private function ensureType(
        string $name,
        ObservationTypeKind $kind,
        ?int $lookupListId,
        int $sortOrder,
        ?array $speciesFilter,
    ): void {
        $attributes = [
            'kind' => $kind,
            'lookup_list_id' => $lookupListId,
            'sort_order' => $sortOrder,
            'active' => true,
            'species_filter' => $speciesFilter,
        ];

        $existing = ObservationType::query()
            ->where('name', $name)
            ->get()
            ->first(fn (ObservationType $type): bool => $type->species_filter === $speciesFilter);
        if ($existing !== null) {
            $existing->update($attributes);

            return;
        }

        ObservationType::query()->create([
            'name' => $name,
            ...$attributes,
        ]);
    }
}
