<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\Enclosure;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_free_text_enclosures_migrate_to_enclosure_records(): void
    {
        $migration = require database_path('migrations/2026_10_30_100100_move_animal_enclosures_to_enclosures_table.php');
        $migration->down();

        $this->assertTrue(Schema::hasColumn('animals', 'enclosure'));
        $this->assertFalse(Schema::hasColumn('animals', 'enclosure_id'));

        $now = now();
        $row = fn (string $name, ?string $enclosure): array => [
            'name' => $name,
            'species' => 'Corn snake',
            'sex' => 'unknown',
            'enclosure' => $enclosure,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        DB::table('animals')->insert([
            $row('Kaa', 'Viv 3'),
            $row('Nagini', ' viv 3 '),
            $row('Polly', 'Flight A'),
            $row('Uno', null),
            $row('Blank', '   '),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('animals', 'enclosure'));
        $this->assertSame(['Flight A', 'Viv 3'], Enclosure::query()->orderBy('name')->pluck('name')->all());

        $viv = Enclosure::query()->where('name', 'Viv 3')->firstOrFail();
        $this->assertSame(['Kaa', 'Nagini'], $viv->animals()->orderBy('name')->pluck('name')->all());
        $this->assertSame('Flight A', Animal::query()->where('name', 'Polly')->firstOrFail()->enclosure?->name);
        $this->assertNull(Animal::query()->where('name', 'Uno')->firstOrFail()->enclosure_id);
        $this->assertNull(Animal::query()->where('name', 'Blank')->firstOrFail()->enclosure_id);
    }

    public function test_rolling_back_restores_the_free_text_enclosure(): void
    {
        $enclosure = Enclosure::factory()->create(['name' => 'Tortoise pen 2']);
        $animal = Animal::factory()->create(['enclosure_id' => $enclosure->id]);

        $migration = require database_path('migrations/2026_10_30_100100_move_animal_enclosures_to_enclosures_table.php');
        $migration->down();

        $this->assertSame('Tortoise pen 2', DB::table('animals')->where('id', $animal->id)->value('enclosure'));

        $migration->up();

        $this->assertSame($enclosure->id, $animal->refresh()->enclosure_id);
    }

    public function test_placing_an_animal_over_capacity_saves_with_a_warning(): void
    {
        $staff = User::factory()->staff()->create();
        $enclosure = Enclosure::factory()->create(['name' => 'Viv 3', 'capacity' => 1]);
        Animal::factory()->create(['name' => 'Kaa', 'enclosure_id' => $enclosure->id]);

        $response = $this->actingAs($staff)->post(route('animals.store'), [
            'name' => 'Nagini',
            'species' => 'Corn snake',
            'sex' => 'female',
            'enclosure_id' => $enclosure->id,
        ]);

        $animal = Animal::query()->where('name', 'Nagini')->firstOrFail();
        $response->assertRedirect(route('animals.show', $animal))
            ->assertSessionHas('warning', 'Viv 3 is over capacity: 2 / 1 animals.');
        $this->assertSame($enclosure->id, $animal->enclosure_id);
        $this->assertTrue($enclosure->isOverCapacity());

        $this->actingAs($staff)
            ->get(route('enclosures.index'))
            ->assertOk()
            ->assertSee('2 / 1', false)
            ->assertSee('data-over-capacity', false);
    }

    public function test_capacity_counts_only_animals_in_care(): void
    {
        $staff = User::factory()->staff()->create();
        $enclosure = Enclosure::factory()->create(['name' => 'Flight A', 'capacity' => 1]);
        $adopted = Animal::factory()->create(['enclosure_id' => $enclosure->id]);
        Movement::factory()->for($adopted)->create(['type' => MovementType::Adoption]);
        Animal::factory()->create(['enclosure_id' => $enclosure->id, 'deceased_at' => '2026-10-01']);

        $this->actingAs($staff)->post(route('animals.store'), [
            'name' => 'Polly',
            'species' => 'African grey parrot',
            'sex' => 'unknown',
            'enclosure_id' => $enclosure->id,
        ])->assertSessionMissing('warning');

        $this->assertSame(1, $enclosure->occupantCount());
        $this->assertTrue($enclosure->isFull());
        $this->assertFalse($enclosure->isOverCapacity());
    }

    public function test_staff_create_and_edit_enclosures_and_names_are_unique(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('enclosures.store'), [
            'name' => 'Viv 3',
            'zone' => 'Reptile House',
            'size' => '120 x 60 x 60 cm',
            'capacity' => '2',
            'active' => '1',
        ])->assertRedirect();

        $enclosure = Enclosure::query()->firstOrFail();
        $this->assertSame('Reptile House', $enclosure->zone);
        $this->assertSame(2, $enclosure->capacity);

        $this->actingAs($staff)
            ->post(route('enclosures.store'), ['name' => 'Viv 3'])
            ->assertSessionHasErrors('name');

        $this->actingAs($staff)->put(route('enclosures.update', $enclosure), [
            'name' => 'Viv 3',
            'zone' => 'Reptile House',
            'capacity' => '',
            'active' => '0',
        ])->assertRedirect(route('enclosures.show', $enclosure));

        $enclosure->refresh();
        $this->assertNull($enclosure->capacity);
        $this->assertFalse($enclosure->active);
    }

    public function test_volunteers_and_readonly_view_but_cannot_manage_enclosures(): void
    {
        $enclosure = Enclosure::factory()->create(['name' => 'Flight A']);
        Animal::factory()->create(['name' => 'Polly', 'enclosure_id' => $enclosure->id]);

        foreach ([User::factory()->volunteer()->create(), User::factory()->readonly()->create()] as $user) {
            $this->actingAs($user)->get(route('enclosures.show', $enclosure))->assertOk()->assertSee('Polly', false);
            $this->actingAs($user)->get(route('enclosures.create'))->assertForbidden();
            $this->actingAs($user)->get(route('enclosures.edit', $enclosure))->assertForbidden();
            $this->actingAs($user)->post(route('enclosures.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->put(route('enclosures.update', $enclosure), ['name' => 'X'])->assertForbidden();
        }
    }

    public function test_shelter_view_groups_animals_by_enclosure(): void
    {
        $staff = User::factory()->staff()->create();
        $viv = Enclosure::factory()->create(['name' => 'Viv 3', 'zone' => 'Reptile House', 'capacity' => 2]);
        Enclosure::factory()->create(['name' => 'Retired viv', 'active' => false]);
        Animal::factory()->create(['name' => 'Kaa', 'enclosure_id' => $viv->id]);
        Animal::factory()->create(['name' => 'Uno', 'enclosure_id' => null]);

        $this->actingAs($staff)
            ->get(route('animals.shelter', ['group' => 'enclosure']))
            ->assertOk()
            ->assertSee('Reptile House · Viv 3', false)
            ->assertSee('1 / 2', false)
            ->assertSee('Kaa', false)
            ->assertSee('No enclosure', false)
            ->assertSee('Uno', false)
            ->assertDontSee('Retired viv', false);
    }

    public function test_animals_are_found_by_enclosure_name(): void
    {
        $staff = User::factory()->staff()->create();
        $viv = Enclosure::factory()->create(['name' => 'Viv 3']);
        Animal::factory()->create(['name' => 'Kaa', 'enclosure_id' => $viv->id]);
        Animal::factory()->create(['name' => 'Polly']);

        $this->actingAs($staff)
            ->get(route('animals.index', ['q' => 'Viv 3']))
            ->assertOk()
            ->assertSee('Kaa', false)
            ->assertDontSee('Polly', false);
    }
}
