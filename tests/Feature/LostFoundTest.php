<?php

namespace Tests\Feature;

use App\Enums\LostFoundType;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\LostFoundReport;
use App\Models\Movement;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LostFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_lost_report_and_see_likely_matches(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create(['name' => 'Jamie Reporter']);
        $match = Animal::factory()->create([
            'name' => 'Spike',
            'species' => 'Bearded dragon',
            'colour' => 'tan',
            'identifying_code' => 'EX-1001',
            'location' => 'Reptile House',
            'deceased_at' => null,
        ]);
        // Pin matcher fields so factory randomness (e.g. location=Reptile House)
        // cannot give this decoy a positive score and show it as a likely match.
        $nonMatch = Animal::factory()->create([
            'name' => 'Other',
            'species' => 'Corn snake',
            'colour' => 'albino',
            'identifying_code' => 'EX-9999',
            'location' => 'Aviary 1',
            'flags' => null,
            'breed_type' => null,
            'deceased_at' => null,
        ]);

        $response = $this->actingAs($staff)->post(route('lost-found.store'), [
            'type' => LostFoundType::Lost->value,
            'species' => 'Bearded dragon',
            'colour' => 'tan',
            'markings' => 'missing toe tip',
            'identifying_code' => 'EX-1001',
            'location_area' => 'Reptile House',
            'reported_at' => '2026-08-28',
            'person_id' => $person->id,
            'notes' => 'Escaped from carrier at intake desk',
        ]);

        $report = LostFoundReport::query()->first();
        $this->assertNotNull($report);
        $response->assertRedirect(route('lost-found.show', $report));

        $this->assertSame(LostFoundType::Lost, $report->type);
        $this->assertSame('Bearded dragon', $report->species);
        $this->assertSame($person->id, $report->person_id);

        $this->actingAs($staff)
            ->get(route('lost-found.show', $report))
            ->assertOk()
            ->assertSee('Likely matches', false)
            ->assertSee('Spike', false)
            ->assertSee('Identifying code', false)
            ->assertDontSee(route('animals.show', $nonMatch), false);
    }

    public function test_recently_adopted_animals_are_included_in_matches(): void
    {
        $staff = User::factory()->staff()->create();
        $adopter = Person::factory()->create();
        $adopted = Animal::factory()->create([
            'name' => 'Polly',
            'species' => 'African grey parrot',
            'identifying_code' => 'AV-55',
            'deceased_at' => null,
        ]);
        Movement::factory()->create([
            'animal_id' => $adopted->id,
            'person_id' => $adopter->id,
            'type' => MovementType::Adoption,
            'moved_at' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $report = LostFoundReport::factory()->lost()->create([
            'species' => 'African grey parrot',
            'identifying_code' => 'AV-55',
        ]);

        $this->actingAs($staff)
            ->get(route('lost-found.show', $report))
            ->assertOk()
            ->assertSee('Polly', false)
            ->assertSee('Identifying code', false);
    }

    public function test_staff_can_search_and_filter_reports_by_type(): void
    {
        $staff = User::factory()->staff()->create();
        LostFoundReport::factory()->lost()->create([
            'species' => 'Sulcata tortoise',
            'location_area' => 'Brighton seafront',
        ]);
        LostFoundReport::factory()->found()->create([
            'species' => 'Corn snake',
            'location_area' => 'Lewes park',
        ]);

        $this->actingAs($staff)
            ->get(route('lost-found.index', ['q' => 'Sulcata']))
            ->assertOk()
            ->assertSee('Sulcata tortoise', false)
            ->assertDontSee('Corn snake', false);

        $this->actingAs($staff)
            ->get(route('lost-found.index', ['type' => LostFoundType::Found->value]))
            ->assertOk()
            ->assertSee('Corn snake', false)
            ->assertDontSee('Sulcata tortoise', false);
    }

    public function test_staff_can_confirm_matched_animal_on_update(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Tess', 'species' => 'Corn snake']);
        $report = LostFoundReport::factory()->found()->create([
            'species' => 'Corn snake',
            'matched_animal_id' => null,
        ]);

        $this->actingAs($staff)
            ->put(route('lost-found.update', $report), [
                'type' => LostFoundType::Found->value,
                'species' => 'Corn snake',
                'colour' => $report->colour,
                'markings' => $report->markings,
                'identifying_code' => $report->identifying_code,
                'location_area' => $report->location_area,
                'reported_at' => $report->reported_at->format('Y-m-d'),
                'matched_animal_id' => $animal->id,
                'notes' => 'Confirmed at reception',
            ])
            ->assertRedirect(route('lost-found.show', $report));

        $report->refresh();
        $this->assertSame($animal->id, $report->matched_animal_id);
    }

    public function test_readonly_cannot_create_reports_but_can_view(): void
    {
        $readonly = User::factory()->readonly()->create();
        $report = LostFoundReport::factory()->lost()->create(['species' => 'Visible gecko']);

        $this->actingAs($readonly)
            ->get(route('lost-found.create'))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('lost-found.store'), [
                'type' => LostFoundType::Lost->value,
                'species' => 'Blocked',
                'reported_at' => '2026-08-28',
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(route('lost-found.show', $report))
            ->assertOk()
            ->assertSee('Visible gecko', false);
    }

    public function test_volunteer_cannot_edit_reports(): void
    {
        $volunteer = User::factory()->state(['role' => UserRole::Volunteer])->create();
        $report = LostFoundReport::factory()->create(['species' => 'Locked iguana']);

        $this->actingAs($volunteer)
            ->get(route('lost-found.edit', $report))
            ->assertForbidden();
    }
}
