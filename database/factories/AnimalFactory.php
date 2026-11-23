<?php

namespace Database\Factories;

use App\Enums\AnimalSex;
use App\Enums\CitesAnnex;
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
            'breed_type' => null,
            'sex' => fake()->randomElement(AnimalSex::cases()),
            'date_of_birth' => fake()->optional()->date(),
            'age_years' => fake()->optional()->numberBetween(0, 40),
            'colour' => null,
            'identifying_code' => strtoupper(fake()->bothify('EX-####')),
            'flags' => null,
            'location' => fake()->randomElement(['Quarantine A', 'Reptile House', 'Aviary 1', 'Outdoor Pad']),
            'bonded_animals' => fake()->optional()->firstName(),
            'entry_reason' => null,
            'non_shelter' => false,
            'is_adoptable' => false,
            'deceased_at' => null,
            'death_reason' => null,
            'enclosure_id' => null,
            'cites' => fake()->optional()->randomElement(['Appendix I', 'Appendix II', 'None']),
            'cites_annex' => null,
            'dwa' => fake()->optional()->randomElement(['Yes', 'No', 'Pending']),
            'primary_photo_path' => null,
        ];
    }

    public function annexXExempt(): static
    {
        return $this->state(fn (): array => [
            'cites_annex' => CitesAnnex::A,
            'cites_exempt_annex_x' => true,
            'cites_exemption_note' => 'Captive-bred, breeder records held',
        ]);
    }
}
