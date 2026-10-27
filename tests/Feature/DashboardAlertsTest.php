<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\User;
use App\Services\DashboardAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarantine_alert_counts_living_animals_whose_latest_movement_is_quarantine(): void
    {
        $match = $this->animalWithMovements('Quarantined Gecko', [MovementType::Intake, MovementType::Quarantine]);
        $this->animalWithMovements('Released Gecko', [MovementType::Quarantine, MovementType::Hold]);
        $this->animalWithMovements('Late Gecko', [MovementType::Quarantine], ['deceased_at' => now()]);
        Animal::factory()->create(['name' => 'Never Moved']);

        $this->assertSame(1, app(DashboardAlerts::class)->quarantine()->count);
        $this->assertFilteredAnimals('quarantine', [$match->name]);
    }

    public function test_overdue_medical_alert_counts_animals_with_undue_given_records(): void
    {
        $match = Animal::factory()->create(['name' => 'Overdue Python']);
        MedicalRecord::factory()->for($match)->create(['due_on' => today()->subDay(), 'given_on' => null]);
        MedicalRecord::factory()->for($match)->create(['due_on' => today()->subDays(5), 'given_on' => null]);

        $given = Animal::factory()->create(['name' => 'Given Python']);
        MedicalRecord::factory()->for($given)->create(['due_on' => today()->subDay(), 'given_on' => today()]);

        $dueToday = Animal::factory()->create(['name' => 'Today Python']);
        MedicalRecord::factory()->for($dueToday)->create(['due_on' => today(), 'given_on' => null]);

        $noDate = Animal::factory()->create(['name' => 'Undated Python']);
        MedicalRecord::factory()->for($noDate)->create(['due_on' => null, 'given_on' => null]);

        $deceased = Animal::factory()->create(['name' => 'Late Python', 'deceased_at' => now()]);
        MedicalRecord::factory()->for($deceased)->create(['due_on' => today()->subDay(), 'given_on' => null]);

        $this->assertSame(1, app(DashboardAlerts::class)->overdueMedical()->count);
        $this->assertFilteredAnimals('medical_overdue', [$match->name]);
    }

    public function test_cites_alert_counts_animals_in_care_with_a_cites_reference(): void
    {
        Animal::factory()->create(['name' => 'Cites Tortoise', 'cites' => 'Appendix II']);
        Animal::factory()->create(['name' => 'Plain Tortoise', 'cites' => null]);
        Animal::factory()->create(['name' => 'Blank Tortoise', 'cites' => '']);
        Animal::factory()->create(['name' => 'Negative Tortoise', 'cites' => ' None ']);
        Animal::factory()->create(['name' => 'Late Tortoise', 'cites' => 'Appendix I', 'deceased_at' => now()]);
        Animal::factory()->create(['name' => 'Visiting Tortoise', 'cites' => 'Appendix I', 'non_shelter' => true]);
        $this->animalWithMovements('Adopted Tortoise', [MovementType::Intake, MovementType::Adoption], ['cites' => 'Appendix II']);
        $this->animalWithMovements('Fostered Tortoise', [MovementType::Intake, MovementType::Foster], ['cites' => 'Appendix II']);

        $this->assertSame(2, app(DashboardAlerts::class)->citesInCare()->count);
        $this->assertFilteredAnimals('cites', ['Cites Tortoise', 'Fostered Tortoise']);
    }

    public function test_dwa_alert_counts_animals_in_care_with_a_dwa_licence(): void
    {
        Animal::factory()->create(['name' => 'Licensed Caiman', 'dwa' => 'DWA-2026-01']);
        Animal::factory()->create(['name' => 'Unlicensed Caiman', 'dwa' => null]);
        Animal::factory()->create(['name' => 'Declined Caiman', 'dwa' => 'No']);
        $this->animalWithMovements('Transferred Caiman', [MovementType::Transfer], ['dwa' => 'DWA-2026-02']);

        $this->assertSame(1, app(DashboardAlerts::class)->dwaInCare()->count);
        $this->assertFilteredAnimals('dwa', ['Licensed Caiman']);
    }

    public function test_task_alerts_split_due_today_and_overdue_open_tasks(): void
    {
        DiaryTask::factory()->create(['title' => 'Overdue weigh-in', 'due_on' => today()->subDays(2)]);
        DiaryTask::factory()->create(['title' => 'Today bulb check', 'due_on' => today()]);
        DiaryTask::factory()->create(['title' => 'Future clean', 'due_on' => today()->addDay()]);
        DiaryTask::factory()->create(['title' => 'Undated task', 'due_on' => null]);
        DiaryTask::factory()->completed()->create(['title' => 'Finished overdue', 'due_on' => today()->subDay()]);
        DiaryTask::factory()->completed()->create(['title' => 'Finished today', 'due_on' => today()]);

        $alerts = app(DashboardAlerts::class);
        $this->assertSame(1, $alerts->overdueTasks()->count);
        $this->assertSame(1, $alerts->tasksDueToday()->count);

        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->get(route('diary.index', ['due' => 'overdue']))
            ->assertOk()
            ->assertSee('data-active-filter="overdue"', false)
            ->assertSee('Overdue weigh-in')
            ->assertDontSee('Today bulb check')
            ->assertDontSee('Future clean')
            ->assertDontSee('Finished overdue');

        $this->actingAs($staff)
            ->get(route('diary.index', ['due' => 'today']))
            ->assertOk()
            ->assertSee('Today bulb check')
            ->assertDontSee('Overdue weigh-in')
            ->assertDontSee('Finished today');
    }

    public function test_dashboard_shows_each_alert_with_a_link_to_its_filtered_list(): void
    {
        $this->animalWithMovements('Quarantined Gecko', [MovementType::Quarantine]);

        $response = $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('home'))
            ->assertOk();

        foreach (app(DashboardAlerts::class)->all() as $alert) {
            $response->assertSee('data-alert="'.$alert->key.'"', false)
                ->assertSee('href="'.e($alert->url).'"', false)
                ->assertSee($alert->label);
        }

        $response->assertSee('is-active', false);
    }

    public function test_unknown_filters_are_ignored(): void
    {
        Animal::factory()->create(['name' => 'Any Gecko']);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('animals.index', ['filter' => 'nonsense']))
            ->assertOk()
            ->assertSee('Any Gecko')
            ->assertDontSee('data-active-filter', false);
    }

    /**
     * @param  list<MovementType>  $types
     * @param  array<string, mixed>  $attributes
     */
    private function animalWithMovements(string $name, array $types, array $attributes = []): Animal
    {
        $animal = Animal::factory()->create(['name' => $name, ...$attributes]);

        foreach ($types as $offset => $type) {
            Movement::factory()->for($animal)->create([
                'type' => $type,
                'moved_at' => today()->subDays(count($types) - $offset),
            ]);
        }

        return $animal;
    }

    /**
     * @param  list<string>  $expectedNames
     */
    private function assertFilteredAnimals(string $filter, array $expectedNames): void
    {
        $response = $this->actingAs(User::factory()->staff()->create())
            ->get(route('animals.index', ['filter' => $filter]))
            ->assertOk()
            ->assertSee('data-active-filter="'.$filter.'"', false);

        $listed = $response->viewData('animals')->pluck('name')->sort()->values()->all();
        sort($expectedNames);

        $this->assertSame($expectedNames, $listed);
    }
}
