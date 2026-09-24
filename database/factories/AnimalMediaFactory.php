<?php

namespace Database\Factories;

use App\Enums\AnimalMediaKind;
use App\Models\Animal;
use App\Models\AnimalMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimalMedia>
 */
class AnimalMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'disk_path' => 'media/animals/1/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => AnimalMediaKind::Photo,
            'uploaded_by' => User::factory()->staff(),
        ];
    }

    public function document(): static
    {
        return $this->state(fn (): array => [
            'disk_path' => 'media/animals/1/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'kind' => AnimalMediaKind::Document,
        ]);
    }
}
