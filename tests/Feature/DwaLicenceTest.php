<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\DwaInspectionOutcome;
use App\Enums\MovementType;
use App\Models\Animal;
use App\Models\DiaryTask;
use App\Models\DwaInspection;
use App\Models\DwaLicence;
use App\Models\Movement;
use App\Models\User;
use App\Services\DashboardAlerts;
use App\Services\ReminderSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DwaLicenceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_renewal_reminder_is_created_30_days_before_expiry_and_not_before(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        $licence = DwaLicence::factory()->create([
            'licence_number' => 'DWA-2026-07',
            'issuing_authority' => 'Bristol City Council',
            'expires_on' => '2026-11-30',
        ]);
        $sync = app(ReminderSync::class);

        $this->assertSame(0, $sync->sync());
        $this->assertSame(0, DiaryTask::query()->count());

        Carbon::setTestNow('2026-10-31 09:00:00');
        $this->assertSame(1, $sync->sync());
        $this->assertSame(0, $sync->sync());
        $this->artisan('easm:reminders')->expectsOutput('0 reminder tasks created.')->assertSuccessful();

        $task = DiaryTask::query()->sole();
        $this->assertSame('Renew DWA licence DWA-2026-07', $task->title);
        $this->assertSame('2026-10-31', $task->due_on?->toDateString());
        $this->assertSame($licence->reminderKey(), $task->reminder_key);
        $this->assertStringContainsString('Bristol City Council; expires on 30/11/2026', (string) $task->body);
        $this->assertTrue(DiaryTask::query()->dueToday()->exists());

        $task->update(['completed_at' => now()]);
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->assertSame(0, $sync->sync());
    }

    public function test_renewing_closes_the_old_reminder_and_starts_a_new_cycle(): void
    {
        Carbon::setTestNow('2026-11-10 09:00:00');
        $staff = User::factory()->staff()->create();
        $licence = DwaLicence::factory()->create(['expires_on' => '2026-11-30']);
        app(ReminderSync::class)->sync();

        $this->actingAs($staff)->put(route('compliance.dwa.update', $licence), [
            'licence_number' => $licence->licence_number,
            'issuing_authority' => $licence->issuing_authority,
            'issued_on' => '2026-11-10',
            'expires_on' => '2027-11-30',
        ])->assertRedirect(route('compliance.dwa.show', $licence));

        $this->assertNotNull(DiaryTask::query()->sole()->completed_at);
        $this->assertSame(0, app(ReminderSync::class)->sync());
        $this->assertSame(0, app(DashboardAlerts::class)->dwaRenewals()->count);

        Carbon::setTestNow('2027-11-01 09:00:00');
        $this->assertSame(1, app(ReminderSync::class)->sync());
        $this->assertSame(1, DiaryTask::query()->open()->count());
    }

    public function test_licence_is_linked_to_its_animals_on_both_sides(): void
    {
        $staff = User::factory()->staff()->create();
        $caiman = Animal::factory()->create(['name' => 'Snappy', 'species' => 'Spectacled caiman']);
        $serval = Animal::factory()->create(['name' => 'Spots', 'species' => 'Serval']);
        $other = Animal::factory()->create(['name' => 'Unlinked']);

        $this->actingAs($staff)->get(route('compliance.dwa.create'))->assertOk()->assertSee('Snappy', false);

        $this->actingAs($staff)->post(route('compliance.dwa.store'), [
            'licence_number' => 'DWA-2026-01',
            'issuing_authority' => 'Cornwall Council',
            'species_covered' => 'Spectacled caiman; serval',
            'conditions' => "Double-locked enclosure\nAnnual vet check",
            'issued_on' => '2026-01-01',
            'expires_on' => '2027-12-31',
            'animal_ids' => [$caiman->id, $serval->id],
        ])->assertRedirect();

        $licence = DwaLicence::query()->sole();
        $this->assertSame(['Snappy', 'Spots'], $licence->animals->pluck('name')->all());

        $this->actingAs($staff)
            ->get(route('compliance.dwa.show', $licence))
            ->assertOk()
            ->assertSee('data-licence-animal="'.$caiman->id.'"', false)
            ->assertSee('data-licence-animal="'.$serval->id.'"', false)
            ->assertDontSee('data-licence-animal="'.$other->id.'"', false)
            ->assertSee('data-not-legal-advice', false);

        $this->actingAs($staff)
            ->get(AnimalTab::Legal->url($caiman))
            ->assertOk()
            ->assertSee('data-dwa-licence="'.$licence->id.'"', false)
            ->assertSee('DWA-2026-01', false);

        $this->actingAs($staff)->put(route('compliance.dwa.update', $licence), [
            'licence_number' => 'DWA-2026-01',
            'issuing_authority' => 'Cornwall Council',
            'expires_on' => '2027-12-31',
            'animal_ids' => [$caiman->id],
        ]);
        $this->assertSame([$caiman->id], $licence->refresh()->animals->pluck('id')->all());
        $this->assertTrue($serval->refresh()->dwaLicences->isEmpty());
    }

    public function test_edit_keeps_departed_animals_that_are_already_linked_selectable(): void
    {
        $staff = User::factory()->staff()->create();
        $licence = DwaLicence::factory()->create();
        $departed = Animal::factory()->create(['name' => 'Moved on caiman']);
        Movement::factory()->for($departed)->create(['type' => MovementType::Transfer]);
        $licence->animals()->attach($departed);
        Animal::factory()->create(['name' => 'Departed elsewhere', 'deceased_at' => now()]);

        $this->actingAs($staff)
            ->get(route('compliance.dwa.edit', $licence))
            ->assertOk()
            ->assertSee('Moved on caiman', false)
            ->assertDontSee('Departed elsewhere', false);
    }

    public function test_staff_record_inspections(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Dana Staff']);
        $licence = DwaLicence::factory()->create();

        $this->actingAs($staff)->post(route('compliance.dwa.inspections.store', $licence), [
            'inspected_on' => '2026-10-01',
            'inspector' => 'Council vet',
            'outcome' => 'action_required',
            'notes' => 'Add second padlock',
        ])->assertRedirect(route('compliance.dwa.show', $licence).'#inspections');

        $inspection = DwaInspection::query()->sole();
        $this->assertSame(DwaInspectionOutcome::ActionRequired, $inspection->outcome);
        $this->assertSame($staff->id, $inspection->recorded_by);

        $this->actingAs($staff)
            ->get(route('compliance.dwa.show', $licence))
            ->assertSee('Add second padlock', false)
            ->assertSee('Action required', false)
            ->assertSee('Dana Staff', false);

        $this->actingAs($staff)
            ->get(route('compliance.dwa.index'))
            ->assertSee('01/10/2026 · Action required', false);
    }

    public function test_dashboard_alert_counts_licences_expired_or_expiring_within_30_days(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        DwaLicence::factory()->create(['licence_number' => 'EXPIRED', 'expires_on' => '2026-10-01']);
        DwaLicence::factory()->create(['licence_number' => 'SOON', 'expires_on' => '2026-11-15']);
        DwaLicence::factory()->create(['licence_number' => 'EDGE', 'expires_on' => '2026-11-29']);
        DwaLicence::factory()->create(['licence_number' => 'LATER', 'expires_on' => '2026-11-30']);

        $alert = app(DashboardAlerts::class)->dwaRenewals();
        $this->assertSame(3, $alert->count);
        $this->assertSame(route('compliance.dwa.index', ['filter' => 'renewal']), $alert->url);

        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)->get(route('home'))->assertSee('data-alert="dwa_renewals"', false);

        $response = $this->actingAs($volunteer)
            ->get($alert->url)
            ->assertOk()
            ->assertSee('data-active-filter="renewal"', false)
            ->assertSee('Expired', false)
            ->assertSee('Expires in 30 days', false);
        $this->assertSame(['EDGE', 'EXPIRED', 'SOON'], $response->viewData('licences')->pluck('licence_number')->sort()->values()->all());
    }

    public function test_only_staff_and_admin_manage_licences(): void
    {
        $licence = DwaLicence::factory()->create();

        foreach ([User::factory()->volunteer()->create(), User::factory()->readonly()->create()] as $user) {
            $this->actingAs($user)->get(route('compliance.dwa.show', $licence))->assertOk()->assertDontSee('Record an inspection', false);
            $this->actingAs($user)->get(route('compliance.dwa.create'))->assertForbidden();
            $this->actingAs($user)->get(route('compliance.dwa.edit', $licence))->assertForbidden();
            $this->actingAs($user)->post(route('compliance.dwa.store'), ['licence_number' => 'X', 'issuing_authority' => 'Y', 'expires_on' => '2027-01-01'])->assertForbidden();
            $this->actingAs($user)->post(route('compliance.dwa.inspections.store', $licence), ['inspected_on' => '2026-10-01', 'outcome' => 'satisfactory'])->assertForbidden();
        }

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('compliance.dwa.store'), ['licence_number' => 'X', 'issuing_authority' => 'Y', 'issued_on' => '2026-06-01', 'expires_on' => '2026-01-01'])
            ->assertSessionHasErrors('expires_on');
    }
}
