<?php

namespace Database\Factories;

use App\Enums\ObservationTypeKind;
use App\Models\LookupList;
use App\Models\ObservationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObservationType>
 */
class ObservationTypeFactory extends Factory
{
    protected $model = ObservationType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'kind' => ObservationTypeKind::FreeText,
            'lookup_list_id' => null,
            'sort_order' => 10,
            'active' => true,
            'species_filter' => null,
        ];
    }

    public function valueList(?LookupList $list = null): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ObservationTypeKind::ValueList,
            'lookup_list_id' => $list?->id ?? LookupList::factory(),
        ]);
    }
}
