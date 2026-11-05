<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-05 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_occupancy_counts_animals_in_care_on_a_date(): void
    {
        $this->seedHistory();

        $response = $this->actingAs(User::factory()->staff()->create())
            ->get(route('reports.occupancy', ['on' => '2026-09-15']))
            ->assertOk()
            ->assertSee('15/09/2026')
            ->assertSee('data-occupancy-total>3 in care', false);

        $names = collect($response->viewData('stays'))->map(fn ($stay) => $stay->animal->name)->sort()->values()->all();
        $this->assertSame(['Basil', 'Iggy', 'Shelly'], $names);
        $this->assertSame(['Bearded dragon' => 1, 'Green iguana' => 1, 'Hermann\'s tortoise' => 1], $response->viewData('bySpecies'));
        $this->assertSame(['No enclosure' => 2, 'Reptile house' => 1], $response->viewData('byEnclosure'));

        $today = $this->get(route('reports.occupancy'))->assertOk();
        $this->assertSame(['Iggy', 'Shelly'], collect($today->viewData('stays'))->map(fn ($stay) => $stay->animal->name)->sort()->values()->all());
    }

    public function test_occupancy_exports_to_csv(): void
    {
        $this->seedHistory();

        $response = $this->actingAs(User::factory()->readonly()->create())
            ->get(route('reports.occupancy.csv', ['on' => '2026-09-15']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", (string) $response->getContent()))));
        $this->assertSame(['Animal ID', 'Name', 'Species', 'Enclosure', 'In care since', 'Days in care'], $lines[0]);
        $this->assertCount(4, $lines);
        $shelly = collect($lines)->firstWhere(1, 'Shelly');
        $this->assertSame(['Hermann\'s tortoise', 'Reptile house', '01/06/2026', '106'], array_slice($shelly, 2));
    }

    public function test_intake_and_outcome_counts_over_a_range(): void
    {
        $this->seedHistory();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.intake-outcome', ['from' => '2026-08-01', 'to' => '2026-10-31']))
            ->assertOk()
            ->assertSee('01/08/2026 to 31/10/2026');

        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['in_care_at_start']);
        $this->assertSame(2, $summary['intakes']);
        $this->assertSame(1, $summary['outcomes']['adoption']);
        $this->assertSame(1, $summary['outcomes']['deceased']);
        $this->assertSame(0, $summary['outcomes']['transfer']);
        $this->assertSame(2, $summary['outcomes_total']);
        $this->assertSame(2, $summary['in_care_at_end']);
        $this->assertSame(['intakes' => 1, 'outcomes' => ['adoption' => 1, 'reclaim' => 0, 'transfer' => 0, 'deceased' => 0]], $summary['species']['Bearded dragon']);

        $csv = $this->get(route('reports.intake-outcome.csv', ['from' => '2026-08-01', 'to' => '2026-10-31']))->assertOk();
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", (string) $csv->getContent()))));
        $this->assertSame(['Species', 'Intakes', 'Adoption', 'Reclaim', 'Transfer', 'Deceased', 'Outcomes total'], $lines[0]);
        $this->assertContains(['Bearded dragon', '1', '1', '0', '0', '0', '1'], $lines);
    }

    public function test_timeline_lists_stays_overlapping_the_range(): void
    {
        $this->seedHistory();
        $returner = Animal::factory()->create(['name' => 'Boomerang', 'species' => 'Corn snake']);
        Movement::factory()->for($returner)->create(['type' => MovementType::Intake, 'moved_at' => '2026-03-01']);
        Movement::factory()->for($returner)->create(['type' => MovementType::Adoption, 'moved_at' => '2026-04-01']);
        Movement::factory()->for($returner)->create(['type' => MovementType::Intake, 'moved_at' => '2026-10-01']);

        $response = $this->actingAs(User::factory()->staff()->create())
            ->get(route('reports.timeline', ['from' => '2026-03-01', 'to' => '2026-11-05']))
            ->assertOk();

        $rows = collect($response->viewData('rows'));
        $this->assertSame(2, $rows->filter(fn ($row) => $row['stay']->animal->is($returner))->count());
        $this->assertNotContains('Old timer', $rows->map(fn ($row) => $row['stay']->animal->name)->all());

        $csv = $this->get(route('reports.timeline.csv', ['from' => '2026-03-01', 'to' => '2026-11-05']))->assertOk();
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", (string) $csv->getContent()))));
        $this->assertSame(['Animal ID', 'Name', 'Species', 'Arrived', 'Left', 'Outcome', 'Days in care'], $lines[0]);
        $this->assertContains([(string) $returner->id, 'Boomerang', 'Corn snake', '01/03/2026', '01/04/2026', 'Adoption', '31'], $lines);
        $this->assertContains([(string) $returner->id, 'Boomerang', 'Corn snake', '01/10/2026', '', 'In care', '35'], $lines);
    }

    public function test_end_date_before_start_is_rejected(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->from(route('reports.index'))
            ->get(route('reports.timeline', ['from' => '2026-10-01', 'to' => '2026-09-01']))
            ->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    public function test_volunteers_cannot_see_reports(): void
    {
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('reports.occupancy.csv'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('home'))->assertOk()->assertDontSee(route('reports.index'));
        $this->actingAs(User::factory()->readonly()->create())->get(route('home'))->assertSee(route('reports.index'));
    }

    private function seedHistory(): void
    {
        $house = Enclosure::factory()->create(['name' => 'Reptile house']);

        $shelly = Animal::factory()->create(['name' => 'Shelly', 'species' => 'Hermann\'s tortoise', 'enclosure_id' => $house->id]);
        Movement::factory()->for($shelly)->create(['type' => MovementType::Intake, 'moved_at' => '2026-06-01']);

        $basil = Animal::factory()->create(['name' => 'Basil', 'species' => 'Bearded dragon']);
        Movement::factory()->for($basil)->create(['type' => MovementType::Intake, 'moved_at' => '2026-08-10']);
        Movement::factory()->for($basil)->create(['type' => MovementType::Adoption, 'moved_at' => '2026-10-01']);

        $iggy = Animal::factory()->create(['name' => 'Iggy', 'species' => 'Green iguana']);
        Movement::factory()->for($iggy)->create(['type' => MovementType::Intake, 'moved_at' => '2026-09-01']);
        Movement::factory()->for($iggy)->create(['type' => MovementType::Quarantine, 'moved_at' => '2026-09-02']);

        $oldTimer = Animal::factory()->create(['name' => 'Old timer', 'species' => 'Corn snake']);
        Movement::factory()->for($oldTimer)->create(['type' => MovementType::Intake, 'moved_at' => '2025-01-01']);
        Movement::factory()->for($oldTimer)->create(['type' => MovementType::Transfer, 'moved_at' => '2025-12-01']);

        $late = Animal::factory()->create(['name' => 'Late', 'species' => 'Royal python', 'deceased_at' => '2026-08-20']);
        Movement::factory()->for($late)->create(['type' => MovementType::Intake, 'moved_at' => '2026-07-01']);

        Animal::factory()->create(['name' => 'Visitor', 'species' => 'Corn snake', 'non_shelter' => true]);
    }
}
