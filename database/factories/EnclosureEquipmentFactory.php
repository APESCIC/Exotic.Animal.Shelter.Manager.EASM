<?php

namespace Database\Factories;

use App\Enums\EquipmentType;
use App\Models\Enclosure;
use App\Models\EnclosureEquipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnclosureEquipment>
 */
class EnclosureEquipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enclosure_id' => Enclosure::factory(),
            'type' => EquipmentType::UvbLamp,
            'name' => fake()->randomElement(['Arcadia T5 12% 54W', 'Arcadia T5 6% 24W', 'ZooMed ReptiSun 10.0']),
            'installed_on' => now()->subDays(30)->toDateString(),
            'replace_every_days' => 365,
            'notes' => null,
            'active' => true,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'installed_on' => now()->subDays(400)->toDateString(),
            'replace_every_days' => 365,
        ]);
    }
}
