<?php

namespace Tests\Feature;

use App\Enums\WaitingListStatus;
use App\Enums\WaitingListUrgency;
use App\Models\Person;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_sorts_by_urgency_then_longest_waiting(): void
    {
        WaitingListEntry::factory()->create(['species' => 'Low old', 'urgency' => WaitingListUrgency::Low, 'added_on' => '2026-01-01']);
        WaitingListEntry::factory()->create(['species' => 'Normal new', 'urgency' => WaitingListUrgency::Normal, 'added_on' => '2026-10-01']);
        WaitingListEntry::factory()->create(['species' => 'Urgent new', 'urgency' => WaitingListUrgency::Urgent, 'added_on' => '2026-10-20']);
        WaitingListEntry::factory()->create(['species' => 'Normal old', 'urgency' => WaitingListUrgency::Normal, 'added_on' => '2026-03-01']);
        WaitingListEntry::factory()->create(['species' => 'High', 'urgency' => WaitingListUrgency::High, 'added_on' => '2026-09-01']);
        WaitingListEntry::factory()->create(['species' => 'Urgent old', 'urgency' => WaitingListUrgency::Urgent, 'added_on' => '2026-08-01']);

        $response = $this->actingAs(User::factory()->readonly()->create())
            ->get(route('waiting-list.index'))
            ->assertOk()
            ->assertSee('badge-dwa', false);

        $this->assertSame(
            ['Urgent old', 'Urgent new', 'High', 'Normal old', 'Normal new', 'Low old'],
            $response->viewData('entries')->pluck('species')->all(),
        );
        $this->assertSame(
            ['Urgent old', 'Urgent new', 'High', 'Normal old', 'Normal new', 'Low old'],
            WaitingListEntry::query()->byUrgency()->pluck('species')->all(),
        );
    }

    public function test_staff_add_an_entry_for_a_contact_or_a_new_name(): void
    {
        $staff = User::factory()->staff()->create();
        $person = Person::factory()->create(['name' => 'Owner Person']);

        $this->actingAs($staff)->get(route('waiting-list.create'))->assertOk();

        $this->actingAs($staff)->post(route('waiting-list.store'), [
            'type' => 'surrender',
            'urgency' => 'urgent',
            'species' => 'Burmese python',
            'person_id' => $person->id,
            'added_on' => '2026-10-27',
            'details' => 'Owner moving abroad on 10/11',
        ])->assertRedirect(route('waiting-list.index'));

        $this->actingAs($staff)->post(route('waiting-list.store'), [
            'type' => 'adoption',
            'urgency' => 'low',
            'species' => 'Leopard gecko',
            'contact_name' => 'Walk-in Visitor',
            'contact_phone' => '07700 900123',
            'added_on' => '2026-10-27',
        ])->assertRedirect(route('waiting-list.index'));

        $this->actingAs($staff)
            ->post(route('waiting-list.store'), ['type' => 'adoption', 'urgency' => 'low', 'species' => 'Gecko', 'added_on' => '2026-10-27'])
            ->assertSessionHasErrors(['person_id', 'contact_name']);

        $this->assertSame(2, WaitingListEntry::query()->count());
        $first = WaitingListEntry::query()->where('species', 'Burmese python')->firstOrFail();
        $this->assertSame(WaitingListStatus::Open, $first->status);
        $this->assertSame($staff->id, $first->created_by);

        $this->actingAs($staff)
            ->get(route('waiting-list.index'))
            ->assertSee('Owner Person', false)
            ->assertSee('Walk-in Visitor', false)
            ->assertSee('07700 900123', false);
    }

    public function test_closing_and_reopening_entries(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = WaitingListEntry::factory()->create(['species' => 'Corn snake']);

        $this->actingAs($staff)
            ->post(route('waiting-list.close', $entry), ['status' => 'placed', 'closed_reason' => 'Taken in'])
            ->assertRedirect(route('waiting-list.index'));

        $entry->refresh();
        $this->assertSame(WaitingListStatus::Placed, $entry->status);
        $this->assertSame(today()->toDateString(), $entry->closed_on->toDateString());

        $this->actingAs($staff)->get(route('waiting-list.index'))->assertDontSee('Corn snake', false);
        $this->actingAs($staff)->get(route('waiting-list.index', ['status' => 'placed']))->assertSee('Corn snake', false);

        $this->actingAs($staff)
            ->post(route('waiting-list.close', $entry), ['status' => 'open'])
            ->assertSessionHasErrors('status');

        $this->actingAs($staff)->post(route('waiting-list.reopen', $entry));
        $entry->refresh();
        $this->assertSame(WaitingListStatus::Open, $entry->status);
        $this->assertNull($entry->closed_on);
    }

    public function test_staff_can_edit_urgency(): void
    {
        $staff = User::factory()->staff()->create();
        $entry = WaitingListEntry::factory()->create(['urgency' => WaitingListUrgency::Low]);

        $this->actingAs($staff)->get(route('waiting-list.edit', $entry))->assertOk();
        $this->actingAs($staff)->put(route('waiting-list.update', $entry), [
            'type' => 'surrender',
            'urgency' => 'urgent',
            'species' => $entry->species,
            'contact_name' => $entry->contact_name,
            'added_on' => '2026-10-01',
        ])->assertRedirect();

        $this->assertSame(WaitingListUrgency::Urgent, $entry->refresh()->urgency);
    }

    public function test_readonly_and_volunteers_can_view_but_not_change(): void
    {
        $entry = WaitingListEntry::factory()->create();

        foreach ([User::factory()->readonly()->create(), User::factory()->volunteer()->create()] as $user) {
            $this->actingAs($user)->get(route('waiting-list.index'))->assertOk()->assertDontSee('Add to waiting list', false);
            $this->actingAs($user)->get(route('waiting-list.create'))->assertForbidden();
            $this->actingAs($user)->get(route('waiting-list.edit', $entry))->assertForbidden();
            $this->actingAs($user)
                ->post(route('waiting-list.store'), ['type' => 'adoption', 'urgency' => 'low', 'species' => 'X', 'contact_name' => 'Y', 'added_on' => '2026-10-27'])
                ->assertForbidden();
            $this->actingAs($user)->post(route('waiting-list.close', $entry), ['status' => 'removed'])->assertForbidden();
        }
    }
}
