<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiaryTask>
 */
class DiaryTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'assigned_to' => User::factory()->staff(),
            'created_by' => User::factory()->staff(),
            'title' => fake()->randomElement(['Check UVB bulb', 'Weigh animal', 'Clean enclosure', 'Follow up quarantine']),
            'body' => fake()->optional()->sentence(),
            'due_on' => fake()->optional()->date(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'completed_at' => now(),
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (): array => [
            'animal_id' => null,
        ]);
    }
}
