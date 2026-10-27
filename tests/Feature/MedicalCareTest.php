<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\MedicalRecordType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\Diet;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_treatment_plan_and_mark_dose_given(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Spike', 'species' => 'Bearded dragon']);

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), [
                'type' => MedicalRecordType::Treatment->value,
                'name' => 'Ivermectin course',
                'due_on' => '2026-09-05',
                'notes' => 'Dose 1 of 3',
            ])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $record = MedicalRecord::query()->where('animal_id', $animal->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(MedicalRecordType::Treatment, $record->type);
        $this->assertNull($record->given_on);

        $this->actingAs($staff)
            ->put(route('medical.update', $record), [
                'type' => MedicalRecordType::Treatment->value,
                'name' => 'Ivermectin course',
                'due_on' => '2026-09-05',
                'given_on' => '2026-09-05',
                'expires_on' => '',
                'notes' => 'Dose 1 of 3 given',
            ])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $record->refresh();
        $this->assertSame('2026-09-05', $record->given_on?->format('Y-m-d'));

        $this->actingAs($staff)
            ->get(AnimalTab::Health->url($animal))
            ->assertOk()
            ->assertSee('Medical', false)
            ->assertSee('Ivermectin course', false)
            ->assertSee('Treatment', false)
            ->assertSee('05/09/2026', false);
    }

    public function test_staff_can_record_vaccination_test_diet_and_observation(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Alex Staff']);
        $animal = Animal::factory()->create(['name' => 'Polly', 'species' => 'African grey parrot']);

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), [
                'type' => MedicalRecordType::Vaccination->value,
                'name' => 'PBFD vaccine',
                'due_on' => '2026-09-01',
                'given_on' => '2026-09-01',
                'expires_on' => '2027-09-01',
            ])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), [
                'type' => MedicalRecordType::Test->value,
                'name' => 'Cloacal swab',
                'due_on' => '2026-09-02',
            ])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.diets.store', $animal), [
                'name' => 'Pellet + fresh veg',
                'details' => 'Morning pellets, afternoon greens',
                'started_on' => '2026-09-01',
            ])
            ->assertRedirect(AnimalTab::Husbandry->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.observations.store', $animal), [
                'observed_on' => '2026-09-03',
                'body' => 'Bright, eating well, droppings normal',
            ])
            ->assertRedirect(AnimalTab::Log->url($animal));

        $this->assertSame(2, MedicalRecord::query()->where('animal_id', $animal->id)->count());
        $this->assertSame(1, Diet::query()->where('animal_id', $animal->id)->count());

        $this->actingAs($staff)
            ->get(AnimalTab::Health->url($animal))
            ->assertOk()
            ->assertSee('PBFD vaccine', false)
            ->assertSee('Cloacal swab', false);

        $this->actingAs($staff)
            ->get(AnimalTab::Husbandry->url($animal))
            ->assertOk()
            ->assertSee('Pellet + fresh veg', false);

        $this->actingAs($staff)
            ->get(AnimalTab::Log->url($animal))
            ->assertOk()
            ->assertSee('Bright, eating well', false)
            ->assertSee('Alex Staff', false);
    }

    public function test_staff_can_end_a_diet(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $diet = Diet::factory()->create([
            'animal_id' => $animal->id,
            'name' => 'Nectar feed',
            'started_on' => '2026-08-01',
            'ended_on' => null,
        ]);

        $this->actingAs($staff)
            ->put(route('diets.update', $diet), [
                'name' => 'Nectar feed',
                'details' => '',
                'started_on' => '2026-08-01',
                'ended_on' => '2026-09-01',
                'notes' => 'Switched to pellets',
            ])
            ->assertRedirect(AnimalTab::Husbandry->url($animal));

        $diet->refresh();
        $this->assertSame('2026-09-01', $diet->ended_on?->format('Y-m-d'));
    }

    public function test_readonly_cannot_create_medical_but_can_view(): void
    {
        $readonly = User::factory()->readonly()->create();
        $animal = Animal::factory()->create(['name' => 'Tess']);
        MedicalRecord::factory()->ofType(MedicalRecordType::Vaccination)->create([
            'animal_id' => $animal->id,
            'name' => 'Visible vax',
        ]);

        $this->actingAs($readonly)
            ->get(route('animals.medical.create', $animal))
            ->assertForbidden();

        $this->actingAs($readonly)
            ->post(route('animals.medical.store', $animal), [
                'type' => MedicalRecordType::Treatment->value,
                'name' => 'Blocked',
                'due_on' => '2026-09-05',
            ])
            ->assertForbidden();

        $this->actingAs($readonly)
            ->get(AnimalTab::Health->url($animal))
            ->assertOk()
            ->assertSee('Visible vax', false)
            ->assertDontSee('Add medical record', false);
    }

    public function test_volunteer_cannot_edit_medical_or_diets(): void
    {
        $volunteer = User::factory()->state(['role' => UserRole::Volunteer])->create();
        $animal = Animal::factory()->create();
        $record = MedicalRecord::factory()->create(['animal_id' => $animal->id]);
        $diet = Diet::factory()->create(['animal_id' => $animal->id]);

        $this->actingAs($volunteer)
            ->get(route('medical.edit', $record))
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->get(route('diets.edit', $diet))
            ->assertForbidden();

        $this->actingAs($volunteer)
            ->get(route('animals.observations.create', $animal))
            ->assertForbidden();
    }
}
