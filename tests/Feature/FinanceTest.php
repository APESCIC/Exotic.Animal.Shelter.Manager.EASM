<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Animal;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Sponsorship;
use App\Models\User;
use App\Support\Money;
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
            ])
            ->assertRedirect(route('people.show', $person));

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(1250, $payment->amount_pence);
        $this->assertSame($staff->id, $payment->created_by);
        $this->assertSame('2026-10-20', $payment->received_on->toDateString());

        $this->actingAs($staff)
            ->get(route('people.show', $person))
            ->assertOk()
            ->assertSee('£12.50', false)
            ->assertSee('Ziggy', false);
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
        $this->actingAs($readonly)
            ->post(route('payments.store'), ['type' => 'donation', 'method' => 'cash', 'amount' => '1', 'received_on' => '2026-10-01'])
            ->assertForbidden();
        $this->actingAs($readonly)->get(route('sponsorships.create'))->assertForbidden();
    }
}
