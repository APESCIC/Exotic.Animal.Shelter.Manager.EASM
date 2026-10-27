<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\Movement;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_intake_quarantine_and_foster_with_history(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Spike', 'species' => 'Bearded dragon']);
        $foster = Person::factory()->create(['name' => 'Alex Foster']);

        $this->actingAs($staff)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Intake->value,
                'moved_at' => '2026-08-01',
                'reason' => 'Surrender',
                'notes' => 'Arrived in carrier',
            ])
            ->assertRedirect(AnimalTab::Movements->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Quarantine->value,
                'moved_at' => '2026-08-01',
                'reason' => 'Exotic intake quarantine',
            ])
            ->assertRedirect(AnimalTab::Movements->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Foster->value,
                'moved_at' => '2026-08-15',
                'person_id' => $foster->id,
                'notes' => 'Two-week foster',
            ])
            ->assertRedirect(AnimalTab::Movements->url($animal));

        $this->assertSame(3, Movement::query()->where('animal_id', $animal->id)->count());

        $this->actingAs($staff)
            ->get(AnimalTab::Movements->url($animal))
            ->assertOk()
            ->assertSee('Movements', false)
            ->assertSee('Intake', false)
            ->assertSee('Quarantine', false)
            ->assertSee('Foster', false)
            ->assertSee('Alex Foster', false)
            ->assertSee('Exotic intake quarantine', false);
    }

    public function test_deceased_movement_syncs_animal_deceased_fields(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create([
            'name' => 'Tess',
            'deceased_at' => null,
            'death_reason' => null,
        ]);

        $this->actingAs($staff)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Deceased->value,
                'moved_at' => '2026-08-20',
                'reason' => 'Natural causes',
                'notes' => 'Found overnight',
            ])
            ->assertRedirect(AnimalTab::Movements->url($animal));

        $animal->refresh();
        $this->assertSame('2026-08-20', $animal->deceased_at?->format('Y-m-d'));
        $this->assertSame('Natural causes', $animal->death_reason);

        $this->actingAs($staff)
            ->get(AnimalTab::Movements->url($animal))
            ->assertOk()
            ->assertSee('Deceased', false)
            ->assertSee('Natural causes', false);
    }

    public function test_staff_can_record_all_movement_types(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $person = Person::factory()->create();

        foreach (MovementType::cases() as $type) {
            $payload = [
                'type' => $type->value,
                'moved_at' => '2026-08-10',
                'reason' => $type->label().' reason',
            ];

            if ($type->typicallyNeedsPerson()) {
                $payload['person_id'] = $person->id;
            }

            $this->actingAs($staff)
                ->post(route('animals.movements.store', $animal), $payload)
                ->assertRedirect(AnimalTab::Movements->url($animal));
        }

        $this->assertSame(count(MovementType::cases()), Movement::query()->where('animal_id', $animal->id)->count());
    }

    public function test_readonly_cannot_record_movements_but_can_view_history(): void
    {
        $readonly = User::factory()->readonly()->create();
        $animal = Animal::factory()->create(['name' => 'Visible']);
        Movement::factory()->create([
            'animal_id' => $animal->id,
            'type' => MovementType::Hold,
            'reason' => 'Pending vet check',
        ]);

        $this->actingAs($readonly)
            ->get(route('animals.movements.create', $animal))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Intake->value,
                'moved_at' => '2026-08-01',
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(AnimalTab::Movements->url($animal))
            ->assertOk()
            ->assertSee('Hold', false)
            ->assertSee('Pending vet check', false)
            ->assertDontSee('Record movement', false);
    }

    public function test_volunteer_cannot_record_movements(): void
    {
        $volunteer = User::factory()->state(['role' => UserRole::Volunteer])->create();
        $animal = Animal::factory()->create();

        $this->actingAs($volunteer)
            ->get(route('animals.movements.create', $animal))
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->post(route('animals.movements.store', $animal), [
                'type' => MovementType::Adoption->value,
                'moved_at' => '2026-08-01',
            ])
            ->assertForbidden();
    }
}
