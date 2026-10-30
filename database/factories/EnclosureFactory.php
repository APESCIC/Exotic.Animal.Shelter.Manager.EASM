<?php

namespace Database\Factories;

use App\Models\Enclosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enclosure>
 */
class EnclosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->unique()->bothify('ENC-###')),
            'zone' => fake()->optional()->randomElement(['Reptile House', 'Aviary', 'Quarantine', 'Outdoor']),
            'size' => fake()->optional()->randomElement(['120 x 60 x 60 cm', '4 x 3 m', '90 x 45 x 45 cm']),
            'capacity' => fake()->optional()->numberBetween(1, 6),
            'notes' => null,
            'active' => true,
        ];
    }
}
