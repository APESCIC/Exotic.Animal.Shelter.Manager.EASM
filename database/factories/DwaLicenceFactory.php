<?php

namespace Database\Factories;

use App\Models\DwaLicence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DwaLicence>
 */
class DwaLicenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'licence_number' => strtoupper(fake()->bothify('DWA-####-??')),
            'issuing_authority' => fake()->randomElement(['Bristol City Council', 'Leeds City Council', 'Cornwall Council']),
            'species_covered' => fake()->randomElement(['Spectacled caiman', 'Venomous snakes (Crotalinae)', 'Serval']),
            'conditions' => null,
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'notes' => null,
        ];
    }
}
