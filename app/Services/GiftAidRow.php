<?php

namespace App\Services;

use App\Models\Payment;

final readonly class GiftAidRow
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(
        public Payment $payment,
        public string $firstName,
        public string $lastName,
        public string $house,
        public string $postcode,
        public string $date,
        public string $amount,
        public array $problems,
    ) {}

    public function isClaimable(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return list<string>
     */
    public function toCsv(): array
    {
        return ['', $this->firstName, $this->lastName, $this->house, $this->postcode, '', '', $this->date, $this->amount];
    }
}
