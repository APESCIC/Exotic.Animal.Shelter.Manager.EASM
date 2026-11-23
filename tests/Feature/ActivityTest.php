<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_staff_can_open_activity_and_see_the_widget(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Logged Animal']);

        $this->actingAs($staff);
        $animal->update(['name' => 'Renamed Animal']);

        foreach ([$admin, $staff] as $user) {
            $this->actingAs($user)
                ->get(route('activity'))
                ->assertOk()
                ->assertSee('Recent changes', false)
                ->assertSee('Animal updated', false);

            $this->actingAs($user)
                ->get(route('home'))
                ->assertOk()
                ->assertSee('data-activity-widget', false)
                ->assertSee('All changes', false)
                ->assertSee('Animal updated', false);
        }
    }

    public function test_volunteers_and_readonly_cannot_open_activity_or_see_the_widget(): void
    {
        Animal::factory()->create();

        foreach ([User::factory()->volunteer()->create(), User::factory()->readonly()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('activity'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee('data-activity-widget', false)
                ->assertDontSee('>Recent changes</h2>', false);
        }
    }

    public function test_activity_filters_by_text_type_and_user(): void
    {
        $staff = User::factory()->staff()->create(['name' => 'Alex Staff']);
        $other = User::factory()->staff()->create(['name' => 'Other Staff']);
        $animal = Animal::factory()->create(['name' => 'Filter Me']);

        $this->actingAs($staff);
        $animal->update(['name' => 'Filter Me Updated']);

        $this->actingAs($other);
        Animal::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($staff)
            ->get(route('activity', ['q' => 'Alex']))
            ->assertOk()
            ->assertSee('Animal updated', false)
            ->assertSee('Alex Staff', false);

        $this->actingAs($staff)
            ->get(route('activity', ['type' => (new Animal)->getMorphClass()]))
            ->assertOk()
            ->assertSee('Animal', false);

        $this->actingAs($staff)
            ->get(route('activity', ['user_id' => $staff->id]))
            ->assertOk()
            ->assertSee('Alex Staff', false)
            ->assertSee('Animal updated', false);
    }

    public function test_activity_links_to_the_record_and_paginates(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Link Target']);

        $this->actingAs($staff);
        $animal->update(['location' => 'Pad A']);

        $this->actingAs($staff)
            ->get(route('activity'))
            ->assertOk()
            ->assertSee(route('animals.show', $animal), false);

        AuditLog::query()->delete();
        foreach (range(1, 55) as $i) {
            AuditLog::record($animal, 'updated', ['name' => 'n'.$i], ['name' => 'n'.($i + 1)]);
        }

        $response = $this->actingAs($staff)
            ->get(route('activity'))
            ->assertOk();

        $this->assertLessThanOrEqual(50, $response->viewData('entries')->count());
        $this->assertTrue($response->viewData('entries')->hasPages());
        $this->assertSame(55, $response->viewData('entries')->total());
    }
}
