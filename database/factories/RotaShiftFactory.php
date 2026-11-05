<?php

namespace Database\Factories;

use App\Models\RotaShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<RotaShift>
 */
class RotaShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('now', '+2 weeks'))->setTime(9, 0);

        return [
            'user_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->setTime(17, 0),
            'area' => fake()->optional()->randomElement(['Reptile house', 'Aviary', 'Quarantine', 'Reception']),
            'notes' => null,
            'created_by' => null,
        ];
    }
}
