<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\Movement;
use App\Models\User;
use App\Services\DashboardAlerts;
use App\Services\DashboardShelter;
use App\Services\ShelterView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShelterTest extends TestCase
{
    use RefreshDatabase;

    public function test_shelter_counts_match_the_database(): void
    {
        Animal::factory()->create(['name' => 'On site', 'is_adoptable' => true]);
        Animal::factory()->create(['name' => 'Also on site', 'is_adoptable' => false]);
        $quarantined = Animal::factory()->create(['name' => 'Quarantined']);
        Movement::factory()->for($quarantined)->create(['type' => MovementType::Quarantine]);
        $fostered = Animal::factory()->create(['name' => 'Fostered']);
        Movement::factory()->for($fostered)->create(['type' => MovementType::Foster]);
        $adopted = Animal::factory()->create(['name' => 'Gone']);
        Movement::factory()->for($adopted)->create(['type' => MovementType::Adoption]);

        $counts = app(DashboardShelter::class)->panel(app(ShelterView::class));

        $this->assertSame(4, $counts['on_shelter']);
        $this->assertSame(1, $counts['quarantine']);
        $this->assertSame(1, $counts['available']);
        $this->assertSame(1, $counts['fostered']);
    }

    public function test_home_shows_counts_compact_shelter_and_recent_animals(): void
    {
        $enclosure = Enclosure::factory()->create(['name' => 'Viv 1', 'zone' => 'House', 'capacity' => 2]);
        $animal = Animal::factory()->create([
            'name' => 'Recent Spike',
            'species' => 'Bearded dragon',
            'enclosure_id' => $enclosure->id,
            'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-shelter-count="on_shelter"', false)
            ->assertSee('data-shelter-count="quarantine"', false)
            ->assertSee('data-shelter-count="available"', false)
            ->assertSee('filter=available', false)
            ->assertSee('data-shelter-count="fostered"', false)
            ->assertSee('Shelter view', false)
            ->assertSee('Full shelter view', false)
            ->assertSee('Recently changed animals', false)
            ->assertSee('Recent Spike', false)
            ->assertSee(route('animals.show', $animal), false)
            ->assertSee('House · Viv 1', false);
    }

    public function test_existing_alert_cards_still_render_on_home(): void
    {
        Movement::factory()->for(Animal::factory()->create(['name' => 'Q']))->create([
            'type' => MovementType::Quarantine,
        ]);

        $response = $this->actingAs(User::factory()->volunteer()->create())
            ->get(route('home'))
            ->assertOk();

        foreach (app(DashboardAlerts::class)->all() as $alert) {
            $response->assertSee('data-alert="'.$alert->key.'"', false)
                ->assertSee($alert->label);
        }
    }
}
