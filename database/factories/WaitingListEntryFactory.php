<?php

namespace Database\Factories;

use App\Enums\WaitingListStatus;
use App\Enums\WaitingListType;
use App\Enums\WaitingListUrgency;
use App\Models\WaitingListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitingListEntry>
 */
class WaitingListEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => WaitingListType::Surrender,
            'person_id' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => null,
            'contact_email' => null,
            'species' => 'Bearded dragon',
            'details' => null,
            'urgency' => WaitingListUrgency::Normal,
            'added_on' => '2026-10-01',
            'status' => WaitingListStatus::Open,
        ];
    }
}
