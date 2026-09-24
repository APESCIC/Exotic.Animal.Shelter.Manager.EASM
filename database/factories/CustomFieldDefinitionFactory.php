<?php

namespace Database\Factories;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldDefinition>
 */
class CustomFieldDefinitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'entity' => CustomFieldEntity::Animal,
            'label' => ucfirst($label),
            'slug' => CustomFieldDefinition::makeSlug($label),
            'type' => CustomFieldType::Text,
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function ofType(CustomFieldType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
