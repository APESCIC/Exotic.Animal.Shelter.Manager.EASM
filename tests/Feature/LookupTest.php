<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\LookupList;
use App\Models\LookupValue;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_lookups(): void
    {
        $list = LookupList::factory()->create();

        foreach ([UserRole::Staff, UserRole::Volunteer, UserRole::Readonly] as $role) {
            $user = User::factory()->state(['role' => $role])->create();

            $this->actingAs($user)
                ->get(route('admin.lookups.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.lookups.show', $list))
                ->assertForbidden();
        }
    }

    public function test_admin_can_add_edit_reorder_and_retire_a_value(): void
    {
        $admin = User::factory()->admin()->create();
        $list = LookupList::factory()->create(['key' => 'colour', 'name' => 'Colour']);

        $this->actingAs($admin)
            ->post(route('admin.lookups.values.store', $list), [
                'name' => 'Iridescent',
                'description' => 'Shiny scales',
                'sort_order' => 20,
                'active' => '1',
            ])
            ->assertRedirect(route('admin.lookups.show', $list));

        $value = LookupValue::query()->where('name', 'Iridescent')->first();
        $this->assertNotNull($value);
        $this->assertTrue($value->active);
        $this->assertSame(20, $value->sort_order);

        $this->actingAs($admin)
            ->put(route('admin.lookups.values.update', [$list, $value]), [
                'name' => 'Iridescent',
                'description' => 'Shiny scales',
                'sort_order' => 5,
                'active' => '0',
            ])
            ->assertRedirect(route('admin.lookups.show', $list));

        $value->refresh();
        $this->assertFalse($value->active);
        $this->assertSame(5, $value->sort_order);

        $this->assertSame(
            0,
            LookupValue::query()->whereKey($value->id)->where('active', true)->count(),
            'Retired values must remain in the table rather than being deleted.',
        );
    }

    public function test_creating_updating_and_retiring_writes_audit_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $list = LookupList::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.lookups.values.store', $list), [
                'name' => 'Audited',
                'sort_order' => 1,
                'active' => '1',
            ])
            ->assertRedirect();

        $value = LookupValue::query()->where('name', 'Audited')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $value->getMorphClass(),
            'auditable_id' => $value->id,
            'action' => 'created',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.lookups.values.update', [$list, $value]), [
                'name' => 'Audited',
                'sort_order' => 2,
                'active' => '0',
            ])
            ->assertRedirect();

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', $value->getMorphClass())
                ->where('auditable_id', $value->id)
                ->where('action', 'updated')
                ->exists(),
        );
    }

    public function test_lookup_seeder_is_idempotent_and_seeds_planned_lists(): void
    {
        $this->seed(LookupSeeder::class);
        $this->seed(LookupSeeder::class);

        $keys = LookupList::query()->orderBy('key')->pluck('key')->all();
        $this->assertSame(
            ['breed_type', 'colour', 'death_reason', 'entry_reason', 'flags'],
            $keys,
        );

        $colour = LookupList::key('colour');
        $this->assertNotNull($colour);
        $this->assertGreaterThan(5, $colour->values()->count());
        $this->assertSame(1, LookupList::query()->where('key', 'colour')->count());
    }
}
