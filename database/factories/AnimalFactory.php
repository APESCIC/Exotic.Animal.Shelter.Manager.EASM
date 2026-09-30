<?php

namespace Database\Factories;

use App\Enums\AnimalSex;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(['Bearded dragon', 'Corn snake', 'African grey parrot', 'Sulcata tortoise']),
            'breed_type' => fake()->optional()->word(),
            'sex' => fake()->randomElement(AnimalSex::cases()),
            'date_of_birth' => fake()->optional()->date(),
            'age_years' => fake()->optional()->numberBetween(0, 40),
            'colour' => fake()->optional()->safeColorName(),
            'identifying_code' => strtoupper(fake()->bothify('EX-####')),
            'flags' => fake()->optional()->word(),
            'location' => fake()->randomElement(['Quarantine A', 'Reptile House', 'Aviary 1', 'Outdoor Pad']),
            'bonded_animals' => fake()->optional()->firstName(),
            'entry_reason' => fake()->optional()->sentence(3),
            'non_shelter' => false,
            'is_adoptable' => false,
            'deceased_at' => null,
            'death_reason' => null,
            'enclosure' => fake()->optional()->bothify('ENC-##'),
            'cites' => fake()->optional()->randomElement(['Appendix I', 'Appendix II', 'None']),
            'dwa' => fake()->optional()->randomElement(['Yes', 'No', 'Pending']),
            'primary_photo_path' => null,
        ];
    }
}
