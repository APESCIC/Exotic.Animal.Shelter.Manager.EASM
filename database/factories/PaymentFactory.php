<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'animal_id' => null,
            'type' => PaymentType::Donation,
            'method' => PaymentMethod::BankTransfer,
            'amount_pence' => fake()->numberBetween(500, 10000),
            'received_on' => '2026-10-01',
            'reference' => null,
            'gift_aid_eligible' => false,
            'notes' => null,
        ];
    }
}
