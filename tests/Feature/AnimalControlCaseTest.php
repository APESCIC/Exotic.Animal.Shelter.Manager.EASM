<?php

namespace Tests\Feature;

use App\Enums\AnimalControlCaseStatus;
use App\Models\AnimalControlCase;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnimalControlCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_dispatch_and_close_a_case(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Alex Dispatcher']);
        $person = Person::factory()->create(['name' => 'Sam Caller']);

        $create = $this->actingAs($staff)->post(route('animal-control.store'), [
            'reported_at' => '2026-11-22T10:30',
            'location_area' => 'Lewes High Street',
            'species' => 'Bearded dragon',
            'summary' => 'Loose reptile reported outside shop',
            'notes' => 'Caller waiting on site',
            'reporter_person_id' => $person->id,
            'linked_animal_id' => null,
        ]);

        $case = AnimalControlCase::query()->first();
        $this->assertNotNull($case);
        $create->assertRedirect(route('animal-control.show', $case));
        $this->assertSame(AnimalControlCaseStatus::Open, $case->status);
        $this->assertStringStartsWith('AC-'.now()->format('Y').'-', $case->reference);
        $this->assertSame('Lewes High Street', $case->location_area);
        $this->assertSame($person->id, $case->reporter_person_id);

        $this->actingAs($staff)
            ->post(route('animal-control.dispatch', $case), [
                'assigned_user_id' => $staff->id,
            ])
            ->assertRedirect(route('animal-control.show', $case));

        $case->refresh();
        $this->assertSame(AnimalControlCaseStatus::Dispatched, $case->status);
        $this->assertSame($staff->id, $case->assigned_user_id);
        $this->assertNotNull($case->dispatched_at);

        $this->actingAs($staff)
            ->post(route('animal-control.close', $case), [
                'close_notes' => 'Animal secured and returned to owner.',
            ])
            ->assertRedirect(route('animal-control.show', $case));

        $case->refresh();
        $this->assertSame(AnimalControlCaseStatus::Closed, $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertSame('Animal secured and returned to owner.', $case->close_notes);

        $this->actingAs($staff)
            ->get(route('animal-control.show', $case))
            ->assertOk()
            ->assertSee($case->reference, false)
            ->assertSee('Closed', false);
    }

    public function test_volunteer_cannot_create_a_case(): void
    {
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)
            ->post(route('animal-control.store'), [
                'reported_at' => '2026-11-22T10:30',
                'location_area' => 'Brighton seafront',
                'summary' => 'Should be forbidden',
            ])
            ->assertForbidden();

        $this->assertSame(0, AnimalControlCase::query()->count());
    }

    public function test_staff_can_filter_cases_by_status(): void
    {
        $staff = User::factory()->staff()->create();
        AnimalControlCase::factory()->open()->create([
            'reference' => 'AC-2026-0001',
            'summary' => 'Open callout',
            'location_area' => 'Park',
        ]);
        AnimalControlCase::factory()->closed()->create([
            'reference' => 'AC-2026-0002',
            'summary' => 'Finished callout',
            'location_area' => 'Pier',
            'assigned_user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('animal-control.index', ['status' => AnimalControlCaseStatus::Open->value]))
            ->assertOk()
            ->assertSee('Open callout', false)
            ->assertDontSee('Finished callout', false);
    }

    public function test_signed_in_readonly_can_view_index(): void
    {
        $readonly = User::factory()->readonly()->create();
        AnimalControlCase::factory()->open()->create([
            'reference' => 'AC-2026-0099',
            'summary' => 'Visible case',
        ]);

        $this->actingAs($readonly)
            ->get(route('animal-control.index'))
            ->assertOk()
            ->assertSee('Visible case', false)
            ->assertDontSee('Add case', false);
    }
}
