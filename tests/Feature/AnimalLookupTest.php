<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\LookupList;
use App\Models\LookupValue;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnimalLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_migration_preserves_mixed_case_and_flagged_free_text(): void
    {
        $this->seed(LookupSeeder::class);

        $id = DB::table('animals')->insertGetId([
            'name' => 'Migrate Me',
            'species' => 'Corn snake',
            'sex' => 'unknown',
            'colour' => 'yElLoW',
            'breed_type' => 'Captive bred',
            'entry_reason' => 'Owner surrender',
            'death_reason' => null,
            'flags' => 'Bite risk; Special diet',
            'non_shelter' => 0,
            'is_adoptable' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_22_201000_migrate_animal_fields_to_lookups.php');
        $migration->up();

        $animal = Animal::query()->findOrFail($id);
        $this->assertSame('Yellow', $animal->colour);
        $this->assertSame('Captive bred', $animal->breed_type);
        $this->assertSame('Owner surrender', $animal->entry_reason);
        $this->assertSame(['Bite risk', 'Special diet'], $animal->flags);
    }

    public function test_form_offers_active_values_and_keeps_retired_on_record(): void
    {
        $this->seed(LookupSeeder::class);
        $staff = User::factory()->staff()->create();
        $list = LookupList::key('colour');
        $this->assertNotNull($list);

        $retired = LookupValue::query()->create([
            'lookup_list_id' => $list->id,
            'name' => 'Legacy teal',
            'sort_order' => 999,
            'active' => false,
        ]);

        $animal = Animal::factory()->create([
            'colour' => $retired->name,
            'species' => 'Bearded dragon',
        ]);

        $this->actingAs($staff)
            ->get(route('animals.edit', $animal))
            ->assertOk()
            ->assertSee('Yellow', false)
            ->assertSee('Legacy teal', false)
            ->assertSee('(retired)', false);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => $animal->name,
                'species' => $animal->species,
                'sex' => $animal->sex->value,
                'colour' => 'Legacy teal',
                'flags' => [],
            ])
            ->assertRedirect(route('animals.show', $animal));

        $this->assertSame('Legacy teal', $animal->fresh()->colour);
    }

    public function test_flags_multi_select_and_clear(): void
    {
        $this->seed(LookupSeeder::class);
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['species' => 'Python', 'flags' => ['Venomous']]);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => $animal->name,
                'species' => $animal->species,
                'sex' => $animal->sex->value,
                'flags' => ['Bite risk', 'Escape risk'],
            ])
            ->assertRedirect();

        $this->assertSame(['Bite risk', 'Escape risk'], $animal->fresh()->flags);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => $animal->name,
                'species' => $animal->species,
                'sex' => $animal->sex->value,
            ])
            ->assertRedirect();

        $this->assertSame([], $animal->fresh()->flags);
    }

    public function test_species_and_location_remain_free_text(): void
    {
        $this->seed(LookupSeeder::class);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('animals.store'), [
                'name' => 'Free Text',
                'species' => 'Custom species name 99',
                'sex' => 'unknown',
                'location' => 'Cupboard under the stairs',
            ])
            ->assertRedirect();

        $animal = Animal::query()->where('name', 'Free Text')->firstOrFail();
        $this->assertSame('Custom species name 99', $animal->species);
        $this->assertSame('Cupboard under the stairs', $animal->location);
    }
}
