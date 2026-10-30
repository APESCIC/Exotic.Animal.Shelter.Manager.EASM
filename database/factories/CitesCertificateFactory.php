<?php

namespace Database\Factories;

use App\Enums\CitesCertificateType;
use App\Models\Animal;
use App\Models\CitesCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitesCertificate>
 */
class CitesCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'type' => CitesCertificateType::Article10,
            'certificate_number' => strtoupper(fake()->bothify('GB-A10-####-??')),
            'issued_on' => fake()->date(),
            'notes' => null,
            'file_path' => null,
            'original_name' => null,
            'uploaded_by' => null,
        ];
    }
}
