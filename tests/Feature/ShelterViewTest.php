<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShelterViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_shelter_view_groups_by_species_and_status(): void
    {
        $staff = User::factory()->staff()->create();
        $dragon = Animal::factory()->create(['name' => 'Spike', 'species' => 'Bearded dragon']);
        $snake = Animal::factory()->create(['name' => 'Kaa', 'species' => 'Corn snake']);
        Movement::factory()->for($dragon)->create(['type' => MovementType::Quarantine]);
        Movement::factory()->for($snake)->create(['type' => MovementType::Foster]);

        $this->actingAs($staff)
            ->get(route('animals.shelter', ['group' => 'species']))
            ->assertOk()
            ->assertSee('Bearded dragon', false)
            ->assertSee('Corn snake', false)
            ->assertSee('Spike', false)
            ->assertSee('Kaa', false);

        $this->actingAs($staff)
            ->get(route('animals.shelter', ['group' => 'status']))
            ->assertOk()
            ->assertSee('Quarantine', false)
            ->assertSee('Foster', false)
            ->assertSee('Spike', false)
            ->assertSee('Kaa', false);
    }

    public function test_shelter_list_view_remains_available(): void
    {
        $staff = User::factory()->staff()->create();
        Animal::factory()->create(['name' => 'Listy', 'species' => 'Iguana']);

        $this->actingAs($staff)
            ->get(route('animals.shelter', ['view' => 'list']))
            ->assertOk()
            ->assertSee('Listy', false)
            ->assertSee('Iguana', false)
            ->assertSee('shelter-list', false);
    }

    public function test_empty_enclosures_show_free_spaces(): void
    {
        $staff = User::factory()->staff()->create();
        Enclosure::factory()->create([
            'name' => 'Empty viv',
            'zone' => 'Reptile House',
            'capacity' => 3,
            'active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('animals.shelter', ['group' => 'enclosure']))
            ->assertOk()
            ->assertSee('Reptile House · Empty viv', false)
            ->assertSee('0 / 3', false)
            ->assertSee('3 free spaces', false);
    }

    public function test_shelter_tiles_show_badges_and_photo_placeholder(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create([
            'name' => 'Badgey',
            'species' => 'Monitor',
            'sex' => 'female',
            'dwa' => 'Yes',
            'cites_annex' => 'A',
            'primary_photo_path' => null,
        ]);
        Movement::factory()->for($animal)->create(['type' => MovementType::Quarantine]);

        $this->actingAs($staff)
            ->get(route('animals.shelter'))
            ->assertOk()
            ->assertSee('Badgey', false)
            ->assertSee('Female', false)
            ->assertSee('No photo for Badgey', false)
            ->assertSee('DWA', false)
            ->assertSee('CITES', false)
            ->assertSee('Quarantine', false);
    }
}
