<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Animal;
use App\Models\GiftAidDeclaration;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Sponsorship;
use App\Models\User;
use App\Services\GiftAidSchedule;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_money_is_parsed_and_formatted_in_whole_pence(): void
    {
        $this->assertSame(1250, Money::toPence('12.5'));
        $this->assertSame(1205, Money::toPence('12.05'));
        $this->assertSame(2500, Money::toPence('25'));
        $this->assertSame(1, Money::toPence('0.01'));
        $this->assertSame('£1,234.50', Money::format(123450));
        $this->assertSame('12.05', Money::plain(1205));
    }

    public function test_staff_record_a_donation_against_a_person(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create(['name' => 'Dora Donor']);
        $animal = Animal::factory()->create(['name' => 'Ziggy']);

        $this->actingAs($staff)
            ->post(route('payments.store'), [
                'type' => PaymentType::Donation->value,
                'method' => PaymentMethod::Card->value,
                'amount' => '12.5',
                'received_on' => '2026-10-20',
                'person_id' => $person->id,
                'animal_id' => $animal->id,
                'reference' => 'RCPT-1',
                'gift_aid_eligible' => '1',
            ])
            ->assertRedirect(route('people.show', $person));

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(1250, $payment->amount_pence);
        $this->assertTrue($payment->gift_aid_eligible);
        $this->assertSame($staff->id, $payment->created_by);
        $this->assertSame('2026-10-20', $payment->received_on->toDateString());

        $this->actingAs($staff)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee('£12.50', false)
            ->assertSee('Ziggy', false);
    }

    public function test_fees_are_never_gift_aid_eligible(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('payments.store'), [
            'type' => PaymentType::AdoptionFee->value,
            'method' => PaymentMethod::Cash->value,
            'amount' => '80',
            'received_on' => '2026-10-20',
            'gift_aid_eligible' => '1',
        ])->assertRedirect(route('finance.index'));

        $this->assertFalse(Payment::query()->firstOrFail()->gift_aid_eligible);
    }

    public function test_amounts_must_be_positive_pounds_and_pence(): void
    {
        $staff = User::factory()->staff()->create();

        foreach (['abc', '0', '0.00', '-5', '12.345', '£10'] as $bad) {
            $this->actingAs($staff)
                ->post(route('payments.store'), [
                    'type' => PaymentType::Donation->value,
                    'method' => PaymentMethod::Cash->value,
                    'amount' => $bad,
                    'received_on' => '2026-10-20',
                ])
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_staff_can_edit_a_payment(): void
    {
        $staff = User::factory()->staff()->create();
        $payment = Payment::factory()->create(['amount_pence' => 1000, 'person_id' => null]);

        $this->actingAs($staff)->get(route('payments.edit', $payment))->assertOk()->assertSee('10.00', false);

        $this->actingAs($staff)->put(route('payments.update', $payment), [
            'type' => PaymentType::Donation->value,
            'method' => PaymentMethod::Cheque->value,
            'amount' => '15.75',
            'received_on' => '2026-10-21',
        ])->assertRedirect(route('finance.index'));

        $payment->refresh();
        $this->assertSame(1575, $payment->amount_pence);
        $this->assertSame(PaymentMethod::Cheque, $payment->method);
    }

    public function test_ledger_filters_and_totals_by_type_and_date(): void
    {
        $staff = User::factory()->staff()->create();
        Payment::factory()->create(['type' => PaymentType::Donation, 'amount_pence' => 1000, 'received_on' => '2026-09-01']);
        Payment::factory()->create(['type' => PaymentType::Donation, 'amount_pence' => 2500, 'received_on' => '2026-10-01']);
        Payment::factory()->create(['type' => PaymentType::AdoptionFee, 'amount_pence' => 8000, 'received_on' => '2026-10-02']);

        $this->actingAs($staff)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('£115.00', false)
            ->assertSee('£35.00', false)
            ->assertSee('£80.00', false);

        $this->actingAs($staff)
            ->get(route('finance.index', ['type' => 'donation', 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('£25.00', false)
            ->assertDontSee('£115.00', false)
            ->assertDontSee('£80.00', false);
    }

    public function test_gift_aid_schedule_includes_only_complete_declared_donations(): void
    {
        $staff = User::factory()->staff()->create();

        $declared = Person::factory()->create([
            'name' => 'Mary Jane Smith',
            'address_line1' => '12A High Street',
            'postcode' => 'ab1 2cd',
        ]);
        GiftAidDeclaration::factory()->for($declared)->create(['effective_from' => '2026-05-01']);
        Payment::factory()->for($declared)->create(['amount_pence' => 2000, 'received_on' => '2026-06-15', 'gift_aid_eligible' => true]);
        Payment::factory()->for($declared)->create(['amount_pence' => 3000, 'received_on' => '2026-04-20', 'gift_aid_eligible' => true]);
        Payment::factory()->for($declared)->create(['amount_pence' => 4000, 'received_on' => '2026-06-16', 'gift_aid_eligible' => false]);
        Payment::factory()->for($declared)->create(['type' => PaymentType::AdoptionFee, 'amount_pence' => 5000, 'received_on' => '2026-06-17', 'gift_aid_eligible' => true]);

        $noPostcode = Person::factory()->create(['name' => 'Pat Jones', 'address_line1' => 'Rose Cottage, Lane End', 'postcode' => null]);
        GiftAidDeclaration::factory()->for($noPostcode)->create();
        Payment::factory()->for($noPostcode)->create(['amount_pence' => 1500, 'received_on' => '2026-07-01', 'gift_aid_eligible' => true]);

        $cancelled = Person::factory()->create(['name' => 'Cal Cancelled', 'address_line1' => '1 Road', 'postcode' => 'ZZ1 1ZZ']);
        GiftAidDeclaration::factory()->for($cancelled)->create(['effective_from' => '2026-01-01', 'cancelled_on' => '2026-06-01']);
        Payment::factory()->for($cancelled)->create(['amount_pence' => 1100, 'received_on' => '2026-06-02', 'gift_aid_eligible' => true]);

        $rows = app(GiftAidSchedule::class)->rows('2026-04-06', '2027-04-05');
        $claimable = array_values(array_filter($rows, fn ($row) => $row->isClaimable()));
        $this->assertCount(4, $rows);
        $this->assertCount(1, $claimable);
        $this->assertSame(['', 'Mary Jane', 'Smith', '12A', 'AB1 2CD', '', '', '15/06/26', '20.00'], $claimable[0]->toCsv());

        $page = $this->actingAs($staff)->get(route('gift-aid.index', ['from' => '2026-04-06', 'to' => '2027-04-05']));
        $page->assertOk()
            ->assertSee('Draft only', false)
            ->assertSee('Ready to claim (1)', false)
            ->assertSee('Needs attention (3)', false)
            ->assertSee('No Gift Aid declaration covering this date', false)
            ->assertSee('Missing postcode', false);

        $csv = $this->actingAs($staff)->get(route('gift-aid.csv', ['from' => '2026-04-06', 'to' => '2027-04-05']));
        $csv->assertOk();
        $this->assertStringContainsString('DRAFT-gift-aid-schedule', (string) $csv->headers->get('Content-Disposition'));
        $lines = array_values(array_filter(explode("\n", (string) $csv->getContent())));
        $this->assertCount(2, $lines);
        $this->assertSame(GiftAidSchedule::COLUMNS, str_getcsv($lines[0]));
        $this->assertSame(',"Mary Jane",Smith,12A,"AB1 2CD",,,15/06/26,20.00', $lines[1]);
    }

    public function test_tax_year_starts_on_6_april(): void
    {
        $this->assertSame('2026-04-06', GiftAidSchedule::taxYearStart(CarbonImmutable::parse('2026-10-27'))->toDateString());
        $this->assertSame('2025-04-06', GiftAidSchedule::taxYearStart(CarbonImmutable::parse('2026-04-05'))->toDateString());
        $this->assertSame('2026-04-06', GiftAidSchedule::taxYearStart(CarbonImmutable::parse('2026-04-06'))->toDateString());
    }

    public function test_staff_record_and_cancel_a_gift_aid_declaration(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create();

        $this->actingAs($staff)->get(route('gift-aid-declarations.create', $person))->assertOk();
        $this->actingAs($staff)->post(route('gift-aid-declarations.store', $person), [
            'declared_on' => '2026-10-01',
            'effective_from' => '2026-04-06',
            'method' => 'written',
        ])->assertRedirect(route('people.show', $person));

        $declaration = GiftAidDeclaration::query()->firstOrFail();
        $this->assertTrue($declaration->isActive());
        $this->actingAs($staff)->get(route('people.show', $person))->assertSee('Declaration in place', false);

        $this->actingAs($staff)
            ->post(route('gift-aid-declarations.cancel', $declaration))
            ->assertRedirect(route('people.show', $person));
        $this->assertSame(today()->toDateString(), $declaration->refresh()->cancelled_on->toDateString());
        $this->actingAs($staff)->get(route('people.show', $person))->assertSee('No active Gift Aid declaration', false);
    }

    public function test_sponsorships_show_on_the_animal_and_the_sponsor(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create(['name' => 'Sam Sponsor']);
        $animal = Animal::factory()->create(['name' => 'Ziggy']);

        $this->actingAs($staff)->get(route('sponsorships.create', ['animal' => $animal->id]))->assertOk();
        $this->actingAs($staff)->post(route('sponsorships.store'), [
            'person_id' => $person->id,
            'animal_id' => $animal->id,
            'amount' => '5',
            'frequency' => 'monthly',
            'starts_on' => '2026-10-01',
        ])->assertRedirect(AnimalTab::Overview->url($animal));

        $sponsorship = Sponsorship::query()->firstOrFail();
        $this->assertSame(500, $sponsorship->amount_pence);

        $this->actingAs($staff)->get(AnimalTab::Overview->url($animal))
            ->assertOk()
            ->assertSee('Sam Sponsor', false)
            ->assertSee('£5.00 monthly', false);
        $this->actingAs($staff)->get(route('people.show', $person))->assertSee('Ziggy', false);

        $this->actingAs($staff)->post(route('sponsorships.end', $sponsorship));
        $this->assertSame(today()->toDateString(), $sponsorship->refresh()->ends_on->toDateString());
    }

    public function test_finance_access_by_role(): void
    {
        $payment = Payment::factory()->create(['amount_pence' => 4242]);
        $person = $payment->person;

        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('home'))->assertDontSee(route('finance.index'), false);
        $this->actingAs($volunteer)->get(route('people.show', $person))->assertDontSee('£42.42', false);

        $readonly = User::factory()->readonly()->create();
        $this->actingAs($readonly)->get(route('finance.index'))->assertOk()->assertSee('£42.42', false)->assertDontSee('Record payment', false);
        $this->actingAs($readonly)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($readonly)->get(route('payments.edit', $payment))->assertForbidden();
        $this->actingAs($readonly)->get(route('gift-aid.index'))->assertForbidden();
        $this->actingAs($readonly)->get(route('gift-aid.csv'))->assertForbidden();
        $this->actingAs($readonly)
            ->post(route('payments.store'), ['type' => 'donation', 'method' => 'cash', 'amount' => '1', 'received_on' => '2026-10-01'])
            ->assertForbidden();
        $this->actingAs($readonly)->post(route('gift-aid-declarations.store', $person), [
            'declared_on' => '2026-10-01', 'effective_from' => '2026-10-01', 'method' => 'written',
        ])->assertForbidden();
        $this->actingAs($readonly)->get(route('sponsorships.create'))->assertForbidden();
    }
}
