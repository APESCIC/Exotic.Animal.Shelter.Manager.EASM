<?php

namespace Database\Factories;

use App\Enums\SponsorshipFrequency;
use App\Models\Animal;
use App\Models\Person;
use App\Models\Sponsorship;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sponsorship>
 */
class SponsorshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'animal_id' => Animal::factory(),
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'amount_pence' => 500,
            'frequency' => SponsorshipFrequency::Monthly,
            'notes' => null,
        ];
    }
}
