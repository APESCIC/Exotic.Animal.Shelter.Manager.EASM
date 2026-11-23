<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\ObservationTypeKind;
use App\Models\Animal;
use App\Models\AnimalObservation;
use App\Models\Enclosure;
use App\Models\LookupList;
use App\Models\LookupValue;
use App\Models\ObservationType;
use App\Models\User;
use Database\Seeders\ObservationTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StructuredObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_observation_types_staff_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $list = LookupList::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.observation-types.store'), [
                'name' => 'Eaten',
                'kind' => ObservationTypeKind::ValueList->value,
                'lookup_list_id' => $list->id,
                'sort_order' => 10,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.observation-types.index'));

        $this->assertDatabaseHas('observation_types', ['name' => 'Eaten']);

        $this->actingAs($staff)
            ->post(route('admin.observation-types.store'), [
                'name' => 'Blocked',
                'kind' => ObservationTypeKind::FreeText->value,
            ])
            ->assertForbidden();
    }

    public function test_value_list_and_free_text_render_on_create_form(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['species' => 'Bearded dragon']);
        $list = LookupList::factory()->create();
        $yes = LookupValue::factory()->create(['lookup_list_id' => $list->id, 'name' => 'Yes']);

        ObservationType::factory()->create([
            'name' => 'Eaten',
            'kind' => ObservationTypeKind::ValueList,
            'lookup_list_id' => $list->id,
            'species_filter' => ['Bearded dragon'],
        ]);

        ObservationType::factory()->create([
            'name' => 'Notes',
            'kind' => ObservationTypeKind::FreeText,
            'species_filter' => ['Bearded dragon'],
        ]);

        $this->actingAs($staff)
            ->get(route('animals.observations.create', $animal))
            ->assertOk()
            ->assertSee('Eaten', false)
            ->assertSee('Notes', false)
            ->assertSee('Yes', false);
    }

    public function test_species_filter_hides_types(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['species' => 'Corn snake']);

        ObservationType::factory()->create([
            'name' => 'Shedding',
            'species_filter' => ['Bearded dragon'],
        ]);

        $this->actingAs($staff)
            ->get(route('animals.observations.create', $animal))
            ->assertOk()
            ->assertDontSee('Shedding', false);
    }

    public function test_observation_type_seeder_is_idempotent(): void
    {
        $this->seed(ObservationTypeSeeder::class);
        $first = ObservationType::query()->count();

        $this->seed(ObservationTypeSeeder::class);
        $second = ObservationType::query()->count();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $second);
    }

    public function test_volunteer_can_store_readonly_forbidden(): void
    {
        $volunteer = User::factory()->volunteer()->create();
        $readonly = User::factory()->readonly()->create();
        $animal = Animal::factory()->create();

        $this->actingAs($volunteer)
            ->post(route('animals.observations.store', $animal), [
                'observed_on' => '2026-11-20',
                'body' => 'Ate well',
            ])
            ->assertRedirect(AnimalTab::Log->url($animal));

        $this->actingAs($readonly)
            ->post(route('animals.observations.store', $animal), [
                'observed_on' => '2026-11-20',
                'body' => 'Blocked',
            ])
            ->assertForbidden();
    }

    public function test_bulk_three_ticked_create_three_unticked_skipped(): void
    {
        $staff = User::factory()->staff()->create();
        $enclosure = Enclosure::factory()->create();
        $list = LookupList::factory()->create();
        $value = LookupValue::factory()->create(['lookup_list_id' => $list->id, 'name' => 'Yes']);
        $type = ObservationType::factory()->create([
            'name' => 'Eaten',
            'kind' => ObservationTypeKind::ValueList,
            'lookup_list_id' => $list->id,
        ]);

        $included = Animal::factory()->count(3)->create(['enclosure_id' => $enclosure->id]);
        $skipped = Animal::factory()->create(['enclosure_id' => $enclosure->id]);

        $rows = [];
        foreach ($included as $animal) {
            $rows[$animal->id] = [
                'include' => '1',
                'types' => [$type->id => (string) $value->id],
            ];
        }

        $this->actingAs($staff)
            ->post(route('observations.bulk.store'), [
                'observed_on' => '2026-11-21',
                'enclosure_id' => $enclosure->id,
                'rows' => $rows,
            ])
            ->assertRedirect();

        $this->assertSame(3, AnimalObservation::query()->count());
        $this->assertSame(0, $skipped->observations()->count());
    }

    public function test_author_edit_within_24_hours_after_forbidden_admin_always(): void
    {
        Carbon::setTestNow('2026-11-20 10:00:00');

        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $animal = Animal::factory()->create();
        $observation = AnimalObservation::factory()->create([
            'animal_id' => $animal->id,
            'user_id' => $staff->id,
            'body' => 'Original',
            'observed_on' => '2026-11-20',
        ]);

        $this->actingAs($staff)
            ->put(route('animals.observations.update', [$animal, $observation]), [
                'observed_on' => '2026-11-20',
                'body' => 'Updated soon',
            ])
            ->assertRedirect(AnimalTab::Log->url($animal));

        Carbon::setTestNow('2026-11-22 10:00:00');

        $this->actingAs($staff)
            ->put(route('animals.observations.update', [$animal, $observation->fresh()]), [
                'observed_on' => '2026-11-20',
                'body' => 'Too late',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('animals.observations.update', [$animal, $observation->fresh()]), [
                'observed_on' => '2026-11-20',
                'body' => 'Admin fix',
            ])
            ->assertRedirect(AnimalTab::Log->url($animal));

        Carbon::setTestNow();
    }

    public function test_updates_and_deletes_create_audit_logs(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $observation = AnimalObservation::factory()->create([
            'animal_id' => $animal->id,
            'user_id' => $staff->id,
            'body' => 'Note',
            'observed_on' => '2026-11-20',
        ]);

        $this->actingAs($staff)
            ->put(route('animals.observations.update', [$animal, $observation]), [
                'observed_on' => '2026-11-20',
                'body' => 'Changed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $observation->getMorphClass(),
            'auditable_id' => $observation->id,
            'action' => 'updated',
        ]);

        $this->actingAs($staff)
            ->delete(route('animals.observations.destroy', [$animal, $observation->fresh()]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $observation->getMorphClass(),
            'action' => 'deleted',
        ]);
    }

    public function test_legacy_free_text_displays_on_log_tab(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        AnimalObservation::factory()->create([
            'animal_id' => $animal->id,
            'body' => 'Legacy shed note',
            'observation_type_id' => null,
            'observed_on' => '2026-11-10',
        ]);

        $this->actingAs($staff)
            ->get(AnimalTab::Log->url($animal))
            ->assertOk()
            ->assertSee('Legacy shed note', false);
    }

    public function test_type_filter_limits_to_thirty_day_window(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $type = ObservationType::factory()->create(['name' => 'Eaten']);

        AnimalObservation::factory()->create([
            'animal_id' => $animal->id,
            'observation_type_id' => $type->id,
            'value_text' => 'Recent',
            'observed_on' => now()->subDays(10),
        ]);

        AnimalObservation::factory()->create([
            'animal_id' => $animal->id,
            'observation_type_id' => $type->id,
            'value_text' => 'Old entry',
            'observed_on' => now()->subDays(45),
        ]);

        $this->actingAs($staff)
            ->get(AnimalTab::Log->url($animal).'?observation_type='.$type->id)
            ->assertOk()
            ->assertSee('Recent', false)
            ->assertDontSee('Old entry', false);
    }
}
