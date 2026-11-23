<?php

namespace Database\Factories;

use App\Models\LookupList;
use App\Models\LookupValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LookupValue>
 */
class LookupValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lookup_list_id' => LookupList::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
