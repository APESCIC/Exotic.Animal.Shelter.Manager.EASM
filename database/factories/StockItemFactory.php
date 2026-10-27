<?php

namespace Database\Factories;

use App\Enums\StockCategory;
use App\Models\StockItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockItem>
 */
class StockItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'category' => StockCategory::Food,
            'unit' => 'tubs',
            'quantity' => '10.00',
            'reorder_level' => '2.00',
            'location' => null,
            'notes' => null,
            'active' => true,
        ];
    }

    public function medical(): static
    {
        return $this->state(fn (): array => ['category' => StockCategory::Medical, 'unit' => 'doses']);
    }
}
