<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\AnimalObservation;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_an_animal_writes_an_audit_entry_shown_on_the_audit_tab(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Before']);

        $this->actingAs($staff)
            ->put(route('animals.update', $animal), [
                'name' => 'After',
                'species' => $animal->species,
                'sex' => $animal->sex->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $animal->getMorphClass(),
            'auditable_id' => $animal->id,
            'action' => 'updated',
            'user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(AnimalTab::Audit->url($animal))
            ->assertOk()
            ->assertSee('updated', false)
            ->assertSee('name', false)
            ->assertSee('After', false);
    }

    public function test_person_and_medical_updates_write_audit_entries(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create(['name' => 'Pat']);
        $animal = Animal::factory()->create();

        $this->actingAs($staff);
        $person->update(['name' => 'Pat Updated']);
        MedicalRecord::factory()->for($animal)->create(['name' => 'Vaccine']);

        $this->assertSame(1, AuditLog::query()->where('auditable_type', $person->getMorphClass())->where('action', 'updated')->count());
        $this->assertSame(1, AuditLog::query()->where('auditable_type', (new MedicalRecord)->getMorphClass())->where('action', 'created')->count());

        $this->actingAs($staff)
            ->get(AnimalTab::Audit->url($animal))
            ->assertOk()
            ->assertSee('Medical record', false)
            ->assertSee('created', false);
    }

    public function test_movement_create_update_and_delete_write_audit_rows(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();

        $this->actingAs($staff);
        $movement = Movement::factory()->for($animal)->create(['type' => MovementType::Intake, 'notes' => 'Quiet intake']);
        $movement->update(['type' => MovementType::Hold]);
        $movementId = $movement->id;
        $movement->delete();

        $this->assertSame(1, AuditLog::query()->where('auditable_type', $movement->getMorphClass())->where('action', 'created')->count());
        $this->assertSame(1, AuditLog::query()->where('auditable_type', $movement->getMorphClass())->where('action', 'updated')->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new Movement)->getMorphClass(),
            'auditable_id' => $movementId,
            'action' => 'deleted',
            'user_id' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(AnimalTab::Audit->url($animal))
            ->assertOk()
            ->assertSee('Movement', false);
    }

    public function test_observation_create_update_and_delete_write_audit_rows(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();

        $this->actingAs($staff);
        $observation = AnimalObservation::factory()->for($animal)->create([
            'user_id' => $staff->id,
            'body' => 'First note',
        ]);
        $observation->update(['body' => 'Edited note']);
        $observationId = $observation->id;
        $observation->delete();

        $type = (new AnimalObservation)->getMorphClass();
        $this->assertSame(1, AuditLog::query()->where('auditable_type', $type)->where('action', 'created')->count());
        $this->assertSame(1, AuditLog::query()->where('auditable_type', $type)->where('action', 'updated')->count());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $type,
            'auditable_id' => $observationId,
            'action' => 'deleted',
            'user_id' => $staff->id,
        ]);
    }

    public function test_deleting_animal_person_and_medical_record_writes_audit_rows(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $person = Person::factory()->create(['name' => 'Pat']);
        $medical = MedicalRecord::factory()->for($animal)->create(['name' => 'Jab']);

        $this->actingAs($staff);
        $animalId = $animal->id;
        $personId = $person->id;
        $medicalId = $medical->id;
        $medical->delete();
        $person->delete();
        $animal->delete();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new MedicalRecord)->getMorphClass(),
            'auditable_id' => $medicalId,
            'action' => 'deleted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new Person)->getMorphClass(),
            'auditable_id' => $personId,
            'action' => 'deleted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => (new Animal)->getMorphClass(),
            'auditable_id' => $animalId,
            'action' => 'deleted',
        ]);
    }

    public function test_summary_omits_personal_data_fields(): void
    {
        $entry = new AuditLog([
            'auditable_type' => (new Person)->getMorphClass(),
            'action' => 'updated',
            'new_values' => [
                'name' => 'Pat',
                'email' => 'pat@example.test',
                'phone' => '01234',
                'address' => '1 High Street',
            ],
        ]);

        $summary = $entry->summary();

        $this->assertStringContainsString('Person updated', $summary);
        $this->assertStringContainsString('name', $summary);
        $this->assertStringNotContainsString('email', $summary);
        $this->assertStringNotContainsString('phone', $summary);
        $this->assertStringNotContainsString('address', $summary);
        $this->assertStringNotContainsString('pat@example.test', $summary);
    }
}
