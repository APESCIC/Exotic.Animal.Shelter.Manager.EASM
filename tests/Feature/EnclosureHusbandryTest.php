<?php

namespace Tests\Feature;

use App\Models\DiaryTask;
use App\Models\Enclosure;
use App\Models\EnclosureEquipment;
use App\Models\EnclosureLog;
use App\Models\User;
use App\Services\ReminderSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnclosureHusbandryTest extends TestCase
{
    use RefreshDatabase;

    public function test_volunteers_record_readings_and_logs_are_listed_per_enclosure(): void
    {
        $volunteer = User::factory()->volunteer()->create(['name' => 'Val Volunteer']);
        $viv = Enclosure::factory()->create(['name' => 'Viv 3']);
        $flight = Enclosure::factory()->create(['name' => 'Flight A']);
        EnclosureLog::factory()->for($flight)->create(['notes' => 'Flight reading']);

        $this->actingAs($volunteer)->post(route('enclosures.logs.store', $viv), [
            'recorded_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'temperature_hot_c' => '34.5',
            'temperature_cool_c' => '24',
            'humidity_percent' => '45',
            'notes' => 'Basking spot checked',
        ])->assertRedirect(route('enclosures.show', $viv).'#environment');

        $log = $viv->logs()->firstOrFail();
        $this->assertSame('34.5', (string) $log->temperature_hot_c);
        $this->assertSame(45, $log->humidity_percent);
        $this->assertSame($volunteer->id, $log->user_id);

        $this->actingAs($volunteer)
            ->get(route('enclosures.show', $viv))
            ->assertOk()
            ->assertSee('Basking spot checked', false)
            ->assertSee('34.5 °C', false)
            ->assertSee('24 °C', false)
            ->assertSee('45%', false)
            ->assertSee('Val Volunteer', false)
            ->assertDontSee('Flight reading', false);
    }

    public function test_a_reading_needs_a_value_and_readonly_users_cannot_record(): void
    {
        $viv = Enclosure::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('enclosures.logs.store', $viv), ['recorded_at' => now()->subMinute()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('notes');

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('enclosures.logs.store', $viv), ['recorded_at' => now()->addDay()->format('Y-m-d\TH:i'), 'humidity_percent' => '50'])
            ->assertSessionHasErrors('recorded_at');

        $this->actingAs(User::factory()->readonly()->create())
            ->post(route('enclosures.logs.store', $viv), ['recorded_at' => now()->format('Y-m-d\TH:i'), 'humidity_percent' => '50'])
            ->assertForbidden();

        $this->assertSame(0, EnclosureLog::query()->count());
    }

    public function test_equipment_due_date_follows_fitted_date_and_interval(): void
    {
        $staff = User::factory()->staff()->create();
        $viv = Enclosure::factory()->create();

        $this->actingAs($staff)->post(route('enclosures.equipment.store', $viv), [
            'type' => 'uvb_lamp',
            'name' => 'Arcadia T5 12% 54W',
            'installed_on' => '2026-01-10',
            'replace_every_days' => '365',
            'active' => '1',
        ])->assertRedirect(route('enclosures.show', $viv).'#equipment');

        $lamp = EnclosureEquipment::query()->firstOrFail();
        $this->assertSame('2027-01-10', $lamp->replace_due_on?->toDateString());

        $this->actingAs($staff)
            ->post(route('enclosures.equipment.store', $viv), ['type' => 'uvb_lamp', 'name' => 'No date', 'replace_every_days' => '180'])
            ->assertSessionHasErrors('installed_on');

        $this->actingAs(User::factory()->volunteer()->create())
            ->post(route('enclosures.equipment.store', $viv), ['type' => 'heat_mat', 'name' => 'Mat'])
            ->assertForbidden();
    }

    public function test_an_overdue_uvb_change_creates_exactly_one_diary_task(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        $viv = Enclosure::factory()->create(['name' => 'Viv 3']);
        $lamp = EnclosureEquipment::factory()->for($viv)->create([
            'name' => 'Arcadia T5 12% 54W',
            'installed_on' => '2025-10-01',
            'replace_every_days' => 365,
        ]);
        EnclosureEquipment::factory()->for($viv)->create(['installed_on' => '2026-10-01', 'replace_every_days' => 365]);
        EnclosureEquipment::factory()->for($viv)->overdue()->create(['active' => false]);

        $sync = app(ReminderSync::class);
        $this->assertSame(1, $sync->sync());
        $this->assertSame(0, $sync->sync());

        $this->artisan('easm:reminders')->expectsOutput('0 reminder tasks created.')->assertSuccessful();

        $task = DiaryTask::query()->sole();
        $this->assertSame('Replace UVB lamp in Viv 3', $task->title);
        $this->assertSame($viv->id, $task->enclosure_id);
        $this->assertNull($task->animal_id);
        $this->assertNull($task->assigned_to);
        $this->assertSame('2026-10-01', $task->due_on?->toDateString());
        $this->assertSame($lamp->reminderKey(), $task->reminder_key);
        $this->assertStringContainsString('Arcadia T5 12% 54W was due for replacement on 01/10/2026', (string) $task->body);
        $this->assertTrue($task->due_on->lt(today()));

        $task->update(['completed_at' => now()]);
        $this->assertSame(0, $sync->sync());

        Carbon::setTestNow();
    }

    public function test_dashboard_and_diary_raise_reminders_without_cron(): void
    {
        $staff = User::factory()->staff()->create();
        $viv = Enclosure::factory()->create(['name' => 'Viv 9']);
        EnclosureEquipment::factory()->for($viv)->overdue()->create();

        $this->actingAs($staff)->get(route('home'))->assertOk();
        $this->assertSame(1, DiaryTask::query()->count());

        $this->actingAs($staff)
            ->get(route('diary.index'))
            ->assertOk()
            ->assertSee('Replace UVB lamp in Viv 9', false)
            ->assertSee(route('enclosures.show', $viv), false);
        $this->assertSame(1, DiaryTask::query()->count());

        $this->actingAs($staff)
            ->get(route('enclosures.index'))
            ->assertSee('Equipment due', false);
    }

    public function test_marking_replaced_restarts_the_interval_and_closes_the_reminder(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        $volunteer = User::factory()->volunteer()->create();
        $viv = Enclosure::factory()->create();
        $lamp = EnclosureEquipment::factory()->for($viv)->overdue()->create();
        app(ReminderSync::class)->sync();

        $this->actingAs($volunteer)
            ->post(route('equipment.replaced', $lamp))
            ->assertRedirect(route('enclosures.show', $viv).'#equipment');

        $lamp->refresh();
        $this->assertSame('2026-10-30', $lamp->installed_on?->toDateString());
        $this->assertSame('2027-10-30', $lamp->replace_due_on?->toDateString());
        $this->assertFalse($lamp->isDue());
        $this->assertNotNull(DiaryTask::query()->sole()->completed_at);
        $this->assertSame(0, app(ReminderSync::class)->sync());

        $this->actingAs(User::factory()->readonly()->create())
            ->post(route('equipment.replaced', $lamp))
            ->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_staff_edit_equipment_and_removed_items_stop_reminding(): void
    {
        $staff = User::factory()->staff()->create();
        $lamp = EnclosureEquipment::factory()->overdue()->create();

        $this->actingAs($staff)->get(route('equipment.edit', $lamp))->assertOk();
        $this->actingAs($staff)->put(route('equipment.update', $lamp), [
            'type' => 'uvb_lamp',
            'name' => $lamp->name,
            'installed_on' => $lamp->installed_on->toDateString(),
            'replace_every_days' => '365',
            'active' => '0',
        ])->assertRedirect(route('enclosures.show', $lamp->enclosure_id).'#equipment');

        $this->assertFalse($lamp->refresh()->active);
        $this->assertSame(0, app(ReminderSync::class)->sync());

        $this->actingAs(User::factory()->volunteer()->create())->get(route('equipment.edit', $lamp))->assertForbidden();
    }
}
