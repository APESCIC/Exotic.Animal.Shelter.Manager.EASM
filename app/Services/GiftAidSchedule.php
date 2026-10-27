<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Models\GiftAidDeclaration;
use App\Models\Payment;
use App\Models\Person;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * DRAFT donation rows laid out like the HMRC Charities Online schedule spreadsheet.
 * The column layout has not been checked against the current HMRC specification.
 */
class GiftAidSchedule
{
    public const COLUMNS = [
        'Title',
        'First name or initial',
        'Last name',
        'House name or number',
        'Postcode',
        'Aggregated donations',
        'Sponsored event',
        'Donation date',
        'Amount',
    ];

    /**
     * @return list<GiftAidRow>
     */
    public function rows(string $from, string $to): array
    {
        $claimableTypes = array_values(array_filter(
            PaymentType::cases(),
            fn (PaymentType $type): bool => $type->canClaimGiftAid(),
        ));

        return Payment::query()
            ->with('person.giftAidDeclarations')
            ->where('gift_aid_eligible', true)
            ->whereIn('type', array_map(fn (PaymentType $type): string => $type->value, $claimableTypes))
            ->receivedBetween($from, $to)
            ->orderBy('received_on')
            ->orderBy('id')
            ->get()
            ->map(fn (Payment $payment): GiftAidRow => $this->row($payment))
            ->values()
            ->all();
    }

    /**
     * @param  list<GiftAidRow>  $rows
     */
    public function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::COLUMNS, escape: '');

        foreach ($rows as $row) {
            if ($row->isClaimable()) {
                fputcsv($handle, $row->toCsv(), escape: '');
            }
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * UK tax years start on 6 April.
     */
    public static function taxYearStart(CarbonImmutable $today): CarbonImmutable
    {
        $start = $today->setDate($today->year, 4, 6)->startOfDay();

        return $today->lessThan($start) ? $start->subYear() : $start;
    }

    private function row(Payment $payment): GiftAidRow
    {
        $person = $payment->person;
        $problems = [];

        [$firstName, $lastName] = $this->splitName($person);
        $house = $this->house($person);
        $postcode = strtoupper(trim((string) $person?->postcode));

        if ($person === null) {
            $problems[] = 'No donor recorded';
        } else {
            if (! $this->hasDeclarationCovering($person, $payment)) {
                $problems[] = 'No Gift Aid declaration covering this date';
            }
            if ($firstName === '' || $lastName === '') {
                $problems[] = 'Donor needs a first and last name';
            }
            if ($house === '') {
                $problems[] = 'Missing house name or number';
            }
            if ($postcode === '') {
                $problems[] = 'Missing postcode';
            }
        }

        return new GiftAidRow(
            payment: $payment,
            firstName: $firstName,
            lastName: $lastName,
            house: $house,
            postcode: $postcode,
            date: $payment->received_on->format('d/m/y'),
            amount: Money::plain($payment->amount_pence),
            problems: $problems,
        );
    }

    private function hasDeclarationCovering(Person $person, Payment $payment): bool
    {
        return $person->giftAidDeclarations->contains(
            fn (GiftAidDeclaration $declaration): bool => $declaration->covers($payment->received_on),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(?Person $person): array
    {
        $parts = preg_split('/\s+/', trim((string) $person?->name)) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if (count($parts) < 2) {
            return [$parts[0] ?? '', ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }

    private function house(?Person $person): string
    {
        $line = trim((string) $person?->address_line1);

        if ($line === '') {
            return '';
        }

        if (preg_match('/^(\d+[A-Za-z]?)\b/', $line, $match) === 1) {
            return $match[1];
        }

        return trim(explode(',', $line)[0]);
    }
}
