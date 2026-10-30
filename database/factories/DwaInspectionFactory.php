<?php

namespace Database\Factories;

use App\Enums\DwaInspectionOutcome;
use App\Models\DwaInspection;
use App\Models\DwaLicence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DwaInspection>
 */
class DwaInspectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dwa_licence_id' => DwaLicence::factory(),
            'inspected_on' => now()->subMonths(2)->toDateString(),
            'inspector' => fake()->name(),
            'outcome' => DwaInspectionOutcome::Satisfactory,
            'notes' => null,
            'recorded_by' => null,
        ];
    }
}
