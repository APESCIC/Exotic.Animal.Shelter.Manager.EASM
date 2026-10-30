<?php

namespace Database\Factories;

use App\Models\Enclosure;
use App\Models\EnclosureLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnclosureLog>
 */
class EnclosureLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enclosure_id' => Enclosure::factory(),
            'recorded_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'temperature_hot_c' => fake()->randomFloat(1, 28, 40),
            'temperature_cool_c' => fake()->randomFloat(1, 20, 27),
            'humidity_percent' => fake()->numberBetween(30, 80),
            'notes' => null,
            'user_id' => null,
        ];
    }
}
