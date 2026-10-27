<?php

namespace Database\Factories;

use App\Enums\GiftAidDeclarationMethod;
use App\Models\GiftAidDeclaration;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftAidDeclaration>
 */
class GiftAidDeclarationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'declared_on' => '2026-01-01',
            'effective_from' => '2026-01-01',
            'cancelled_on' => null,
            'method' => GiftAidDeclarationMethod::Written,
            'notes' => null,
        ];
    }
}
