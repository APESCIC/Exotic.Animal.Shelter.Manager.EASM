<?php

namespace Tests\Feature;

use App\Models\RotaShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-05 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_staff_add_edit_and_remove_shifts(): void
    {
        $staff = User::factory()->staff()->create();
        $volunteer = User::factory()->volunteer()->create(['name' => 'Val Volunteer']);

        $this->actingAs($staff)
            ->post(route('rota.store'), [
                'user_id' => $volunteer->id,
                'date' => '2026-11-07',
                'start_time' => '09:00',
                'end_time' => '13:00',
                'area' => 'Aviary',
            ])
            ->assertRedirect(route('rota.index', ['week' => '2026-11-07']));

        $shift = RotaShift::query()->sole();
        $this->assertSame('2026-11-07 09:00', $shift->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-07 13:00', $shift->ends_at->format('Y-m-d H:i'));
        $this->assertSame($staff->id, $shift->created_by);

        $this->actingAs($staff)
            ->put(route('rota.update', $shift), [
                'user_id' => '',
                'date' => '2026-11-07',
                'start_time' => '22:00',
                'end_time' => '06:00',
            ])
            ->assertRedirect();

        $shift->refresh();
        $this->assertNull($shift->user_id);
        $this->assertSame('2026-11-08 06:00', $shift->ends_at->format('Y-m-d H:i'));
        $this->assertSame('22:00–06:00 (next day)', $shift->timeRange());
        $this->assertSame('Open shift', $shift->assigneeName());

        $this->actingAs($staff)->delete(route('rota.destroy', $shift))->assertRedirect();
        $this->assertSame(0, RotaShift::query()->count());
    }

    public function test_invalid_shifts_are_rejected(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post(route('rota.store'), ['date' => '2026-11-07', 'start_time' => '09:00', 'end_time' => '09:00'])
            ->assertSessionHasErrors(['end_time' => 'The shift must end at a different time from when it starts.']);

        $this->actingAs($staff)
            ->post(route('rota.store'), ['date' => '2026-11-07', 'start_time' => '06:00', 'end_time' => '23:00'])
            ->assertSessionHasErrors(['end_time' => 'A shift cannot be longer than 16 hours.']);

        $this->assertSame(0, RotaShift::query()->count());
    }

    public function test_week_view_shows_that_week_only_and_everyone_can_see_it(): void
    {
        $volunteer = User::factory()->volunteer()->create(['name' => 'Val Volunteer']);
        $thisWeek = RotaShift::factory()->for($volunteer)->create(['starts_at' => '2026-11-06 09:00', 'ends_at' => '2026-11-06 12:00', 'area' => 'Reptile house']);
        $nextWeek = RotaShift::factory()->create(['starts_at' => '2026-11-10 09:00', 'ends_at' => '2026-11-10 12:00']);

        $this->actingAs($volunteer)
            ->get(route('rota.index'))
            ->assertOk()
            ->assertSee('Week starting Monday 02/11/2026')
            ->assertSee('data-rota-shift="'.$thisWeek->id.'"', false)
            ->assertDontSee('data-rota-shift="'.$nextWeek->id.'"', false)
            ->assertSee('Val Volunteer')
            ->assertDontSee(route('rota.create'));

        $this->actingAs($volunteer)
            ->get(route('rota.index', ['week' => '2026-11-12']))
            ->assertOk()
            ->assertSee('data-rota-shift="'.$nextWeek->id.'"', false);
    }

    public function test_volunteers_and_read_only_cannot_plan_the_rota(): void
    {
        $shift = RotaShift::factory()->create();

        foreach ([User::factory()->volunteer()->create(), User::factory()->readonly()->create()] as $user) {
            $this->actingAs($user)->get(route('rota.create'))->assertForbidden();
            $this->actingAs($user)->post(route('rota.store'), ['date' => '2026-11-07', 'start_time' => '09:00', 'end_time' => '12:00'])->assertForbidden();
            $this->actingAs($user)->get(route('rota.edit', $shift))->assertForbidden();
            $this->actingAs($user)->delete(route('rota.destroy', $shift))->assertForbidden();
        }

        $this->assertSame(1, RotaShift::query()->count());
    }

    public function test_my_shifts_lists_only_my_upcoming_shifts(): void
    {
        $me = User::factory()->volunteer()->create();
        $mine = RotaShift::factory()->for($me)->create(['starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 12:00']);
        $past = RotaShift::factory()->for($me)->create(['starts_at' => '2026-11-01 09:00', 'ends_at' => '2026-11-01 12:00']);
        $theirs = RotaShift::factory()->create(['starts_at' => '2026-11-08 09:00', 'ends_at' => '2026-11-08 12:00']);

        $this->actingAs($me)
            ->get(route('rota.mine'))
            ->assertOk()
            ->assertSee('data-my-shift="'.$mine->id.'"', false)
            ->assertDontSee('data-my-shift="'.$past->id.'"', false)
            ->assertDontSee('data-my-shift="'.$theirs->id.'"', false);
    }
}
