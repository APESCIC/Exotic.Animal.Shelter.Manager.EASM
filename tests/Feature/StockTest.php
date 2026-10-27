<?php

namespace Tests\Feature;

use App\Enums\AnimalTab;
use App\Enums\StockMovementReason;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DashboardAlerts;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_add_an_item_with_opening_stock_recorded_as_a_count(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('stock.store'), [
            'name' => 'Live crickets (large)',
            'category' => 'food',
            'unit' => 'tubs',
            'opening_quantity' => '12',
            'reorder_level' => '4',
            'location' => 'Food store',
            'active' => '1',
        ])->assertRedirect();

        $item = StockItem::query()->firstOrFail();
        $this->assertSame('12.00', (string) $item->quantity);
        $this->assertSame('4.00', (string) $item->reorder_level);
        $movement = $item->movements()->firstOrFail();
        $this->assertSame(StockMovementReason::Counted, $movement->reason);
        $this->assertSame('12.00', (string) $movement->change);
        $this->assertSame($staff->id, $movement->user_id);

        $this->actingAs($staff)
            ->get(route('stock.show', $item))
            ->assertOk()
            ->assertSee('12 tubs', false)
            ->assertSee('Opening stock', false);
    }

    public function test_ledger_applies_received_used_wasted_and_counted(): void
    {
        $item = StockItem::factory()->create(['quantity' => '10.00']);
        $ledger = app(StockLedger::class);

        $ledger->record($item, StockMovementReason::Received, '5.5');
        $this->assertSame('15.50', (string) $item->refresh()->quantity);

        $ledger->record($item, StockMovementReason::Used, '0.25');
        $this->assertSame('15.25', (string) $item->refresh()->quantity);

        $ledger->record($item, StockMovementReason::Wasted, '1.25');
        $this->assertSame('14.00', (string) $item->refresh()->quantity);

        $movement = $ledger->record($item, StockMovementReason::Counted, '11');
        $this->assertSame('11.00', (string) $item->refresh()->quantity);
        $this->assertSame('-3.00', (string) $movement->change);
        $this->assertSame('11.00', (string) $movement->quantity_after);

        $this->assertSame(4, $item->movements()->count());
    }

    public function test_recording_a_medical_use_decrements_stock_and_links_the_record(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create(['name' => 'Ziggy']);
        $item = StockItem::factory()->medical()->create(['name' => 'Baytril 2.5%', 'quantity' => '10.00', 'reorder_level' => '3.00']);

        $this->actingAs($staff)
            ->get(route('animals.medical.create', $animal))
            ->assertOk()
            ->assertSee('Baytril 2.5%', false)
            ->assertSee('Stock used', false);

        $this->actingAs($staff)->post(route('animals.medical.store', $animal), [
            'type' => 'treatment',
            'name' => 'Baytril course',
            'given_on' => '2026-10-27',
            'stock_item_id' => $item->id,
            'stock_quantity' => '7.5',
        ])->assertRedirect(AnimalTab::Health->url($animal))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'Baytril 2.5% stock is now 2.5 doses'));

        $item->refresh();
        $this->assertSame('2.50', (string) $item->quantity);
        $this->assertTrue($item->isLow());

        $movement = StockMovement::query()->firstOrFail();
        $record = MedicalRecord::query()->firstOrFail();
        $this->assertSame(StockMovementReason::Used, $movement->reason);
        $this->assertSame($record->id, $movement->medical_record_id);
        $this->assertSame($animal->id, $movement->animal_id);
        $this->assertSame('Medical: Baytril course', $movement->notes);
    }

    public function test_medical_record_without_stock_leaves_stock_alone_and_quantity_is_required_with_item(): void
    {
        $staff = User::factory()->staff()->create();
        $animal = Animal::factory()->create();
        $item = StockItem::factory()->medical()->create(['quantity' => '10.00']);

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), ['type' => 'test', 'name' => 'Faecal float'])
            ->assertRedirect(AnimalTab::Health->url($animal));

        $this->actingAs($staff)
            ->post(route('animals.medical.store', $animal), ['type' => 'treatment', 'name' => 'Dose', 'stock_item_id' => $item->id])
            ->assertSessionHasErrors('stock_quantity');

        $this->assertSame(1, MedicalRecord::query()->count());
        $this->assertSame('10.00', (string) $item->refresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_low_stock_alert_counts_active_items_at_or_below_reorder_level(): void
    {
        StockItem::factory()->create(['name' => 'At level', 'quantity' => '2.00', 'reorder_level' => '2.00']);
        StockItem::factory()->create(['name' => 'Below level', 'quantity' => '0.50', 'reorder_level' => '2.00']);
        StockItem::factory()->create(['name' => 'Plenty', 'quantity' => '9.00', 'reorder_level' => '2.00']);
        StockItem::factory()->create(['name' => 'No level', 'quantity' => '0.00', 'reorder_level' => null]);
        StockItem::factory()->create(['name' => 'Retired', 'quantity' => '0.00', 'reorder_level' => '2.00', 'active' => false]);

        $alert = app(DashboardAlerts::class)->lowStock();
        $this->assertSame(2, $alert->count);
        $this->assertSame(route('stock.index', ['filter' => 'low']), $alert->url);

        $volunteer = User::factory()->volunteer()->create();
        $this->actingAs($volunteer)
            ->get(route('home'))
            ->assertSee('data-alert="low_stock"', false);

        $response = $this->actingAs($volunteer)
            ->get($alert->url)
            ->assertOk()
            ->assertSee('data-active-filter="low"', false);
        $this->assertSame(['At level', 'Below level'], $response->viewData('items')->pluck('name')->sort()->values()->all());
    }

    public function test_volunteers_record_changes_but_cannot_manage_items(): void
    {
        $item = StockItem::factory()->create(['quantity' => '3.00']);
        $volunteer = User::factory()->volunteer()->create();

        $this->actingAs($volunteer)
            ->post(route('stock.movements.store', $item), ['reason' => 'received', 'quantity' => '2'])
            ->assertRedirect(route('stock.show', $item));
        $this->assertSame('5.00', (string) $item->refresh()->quantity);

        $this->actingAs($volunteer)->get(route('stock.create'))->assertForbidden();
        $this->actingAs($volunteer)->get(route('stock.edit', $item))->assertForbidden();
        $this->actingAs($volunteer)->put(route('stock.update', $item), ['name' => 'X', 'category' => 'food', 'unit' => 'g'])->assertForbidden();

        $readonly = User::factory()->readonly()->create();
        $this->actingAs($readonly)->get(route('stock.show', $item))->assertOk()->assertDontSee('Record a change', false);
        $this->actingAs($readonly)
            ->post(route('stock.movements.store', $item), ['reason' => 'used', 'quantity' => '1'])
            ->assertForbidden();
    }

    public function test_quantities_are_validated(): void
    {
        $staff = User::factory()->staff()->create();
        $item = StockItem::factory()->create(['quantity' => '3.00']);

        foreach (['abc', '-1', '1.234', '0'] as $bad) {
            $this->actingAs($staff)
                ->post(route('stock.movements.store', $item), ['reason' => 'used', 'quantity' => $bad])
                ->assertSessionHasErrors('quantity');
        }

        $this->actingAs($staff)
            ->post(route('stock.movements.store', $item), ['reason' => 'counted', 'quantity' => '0'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame('0.00', (string) $item->refresh()->quantity);
    }

    public function test_staff_can_edit_item_details_without_changing_quantity(): void
    {
        $staff = User::factory()->staff()->create();
        $item = StockItem::factory()->create(['name' => 'Old name', 'quantity' => '7.00']);

        $this->actingAs($staff)->put(route('stock.update', $item), [
            'name' => 'Dubia roaches',
            'category' => 'food',
            'unit' => 'boxes',
            'reorder_level' => '',
            'active' => '1',
        ])->assertRedirect(route('stock.show', $item));

        $item->refresh();
        $this->assertSame('Dubia roaches', $item->name);
        $this->assertNull($item->reorder_level);
        $this->assertSame('7.00', (string) $item->quantity);
    }
}
