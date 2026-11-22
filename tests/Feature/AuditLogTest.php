<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Models\Animal;
use App\Models\AuditLog;
use App\Models\MedicalRecord;
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
}
