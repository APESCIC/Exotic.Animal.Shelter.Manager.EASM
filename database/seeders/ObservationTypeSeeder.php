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
     * Previous demo-labelled filters from v1.3.0. Untouched rows matching these exactly are upgraded.
     *
     * @var list<string>
     */
    public const LEGACY_REPTILE_SPECIES = [
        'Bearded dragon (demo)',
        'Corn snake (demo)',
        'Sulcata tortoise (demo)',
        'Royal python (demo)',
        'Leopard gecko (demo)',
    ];

    /**
     * @var list<string>
     */
    public const LEGACY_BIRD_SPECIES = [
        'African grey parrot (demo)',
        'Eclectus parrot (demo)',
        'Budgerigar (demo)',
    ];

    /**
     * @var list<string>
     */
    public const REPTILE_SPECIES = [
        'Bearded dragon',
        'Corn snake',
        'Sulcata tortoise',
        'Royal python',
        'Leopard gecko',
    ];

    /**
     * @var list<string>
     */
    public const BIRD_SPECIES = [
        'African grey parrot',
        'Eclectus parrot',
        'Budgerigar',
    ];

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

        $this->ensureType('Eaten', ObservationTypeKind::ValueList, $yesNoPartial->id, 10, self::REPTILE_SPECIES, self::LEGACY_REPTILE_SPECIES);
        $this->ensureType('Drunk', ObservationTypeKind::ValueList, $yesNoPartial->id, 20, self::REPTILE_SPECIES, self::LEGACY_REPTILE_SPECIES);
        $this->ensureType('Faeces', ObservationTypeKind::ValueList, $this->ensureList(
            'observation_faeces',
            'Observation: Faeces',
            ['Normal', 'Soft', 'None seen', 'Not checked'],
        )->id, 30, self::REPTILE_SPECIES, self::LEGACY_REPTILE_SPECIES);
        $this->ensureType('Shedding', ObservationTypeKind::ValueList, $yesNoPartial->id, 40, self::REPTILE_SPECIES, self::LEGACY_REPTILE_SPECIES);
        $this->ensureType('Basking', ObservationTypeKind::ValueList, $yesNoPartial->id, 50, self::REPTILE_SPECIES, self::LEGACY_REPTILE_SPECIES);

        $this->ensureType('Eaten', ObservationTypeKind::ValueList, $yesNoPartial->id, 10, self::BIRD_SPECIES, self::LEGACY_BIRD_SPECIES);
        $this->ensureType('Drunk', ObservationTypeKind::ValueList, $yesNoPartial->id, 20, self::BIRD_SPECIES, self::LEGACY_BIRD_SPECIES);
        $this->ensureType('Droppings', ObservationTypeKind::ValueList, $this->ensureList(
            'observation_droppings',
            'Observation: Droppings',
            ['Normal', 'Soft', 'None seen', 'Not checked'],
        )->id, 30, self::BIRD_SPECIES, self::LEGACY_BIRD_SPECIES);
        $this->ensureType('Vocalisation', ObservationTypeKind::FreeText, null, 40, self::BIRD_SPECIES, self::LEGACY_BIRD_SPECIES);
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
     * @param  list<string>|null  $legacySpeciesFilter
     */
    private function ensureType(
        string $name,
        ObservationTypeKind $kind,
        ?int $lookupListId,
        int $sortOrder,
        ?array $speciesFilter,
        ?array $legacySpeciesFilter = null,
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
            ->first(function (ObservationType $type) use ($speciesFilter, $legacySpeciesFilter): bool {
                if ($type->species_filter === $speciesFilter) {
                    return true;
                }

                return $legacySpeciesFilter !== null && $type->species_filter === $legacySpeciesFilter;
            });

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
