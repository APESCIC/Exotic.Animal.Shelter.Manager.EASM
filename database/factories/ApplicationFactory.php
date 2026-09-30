<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(ApplicationType::cases()),
            'status' => ApplicationStatus::Submitted,
            'animal_id' => null,
            'person_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address_line1' => fake()->optional()->streetAddress(),
            'address_line2' => null,
            'town_city' => fake()->optional()->city(),
            'county' => fake()->optional()->randomElement(['Cornwall', 'Devon', 'Kent', 'Yorkshire']),
            'postcode' => fake()->optional()->bothify('??# #??'),
            'message' => fake()->optional()->sentence(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'status_note' => null,
        ];
    }

    public function adopter(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ApplicationType::Adopter,
        ]);
    }

    public function volunteer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ApplicationType::Volunteer,
        ]);
    }

    public function foster(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ApplicationType::Foster,
        ]);
    }
}
