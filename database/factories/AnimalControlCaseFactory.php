<?php

namespace Database\Factories;

use App\Enums\AnimalControlCaseStatus;
use App\Models\AnimalControlCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimalControlCase>
 */
class AnimalControlCaseFactory extends Factory
{
    protected $model = AnimalControlCase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'AC-'.fake()->unique()->numerify('####-####'),
            'reported_at' => now()->subHours(fake()->numberBetween(1, 48)),
            'status' => AnimalControlCaseStatus::Open,
            'location_area' => fake()->city(),
            'species' => fake()->optional()->randomElement(['Bearded dragon', 'Corn snake', 'Sulcata tortoise']),
            'summary' => fake()->sentence(6),
            'notes' => fake()->optional()->sentence(),
            'reporter_person_id' => null,
            'linked_animal_id' => null,
            'assigned_user_id' => null,
            'dispatched_at' => null,
            'closed_at' => null,
            'close_notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => AnimalControlCaseStatus::Open,
            'assigned_user_id' => null,
            'dispatched_at' => null,
            'closed_at' => null,
            'close_notes' => null,
        ]);
    }

    public function dispatched(): static
    {
        return $this->state(fn (): array => [
            'status' => AnimalControlCaseStatus::Dispatched,
            'dispatched_at' => now()->subHour(),
            'closed_at' => null,
            'close_notes' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => AnimalControlCaseStatus::Closed,
            'dispatched_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'close_notes' => 'Resolved on site.',
        ]);
    }
}
