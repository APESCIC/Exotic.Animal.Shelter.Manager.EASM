<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_id' => CustomFieldDefinition::factory(),
            'animal_id' => Animal::factory(),
            'value' => fake()->word(),
        ];
    }
}
