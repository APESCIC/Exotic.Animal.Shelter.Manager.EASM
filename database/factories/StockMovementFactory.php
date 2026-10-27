<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_item_id' => StockItem::factory(),
            'reason' => StockMovementReason::Received,
            'change' => '1.00',
            'quantity_after' => '11.00',
            'notes' => null,
        ];
    }
}
