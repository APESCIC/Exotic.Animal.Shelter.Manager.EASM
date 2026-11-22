<?php

namespace Database\Factories;

use App\Models\LookupList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LookupList>
 */
class LookupListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name, '_'),
            'name' => ucfirst($name),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
