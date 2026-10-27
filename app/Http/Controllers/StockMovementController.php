<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementReason;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Animal;
use App\Models\StockItem;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;

class StockMovementController extends Controller
{
    public function store(StoreStockMovementRequest $request, StockItem $stockItem, StockLedger $ledger): RedirectResponse
    {
        $ledger->record(
            $stockItem,
            StockMovementReason::from($request->string('reason')->toString()),
            $request->string('quantity')->toString(),
            $request->user(),
            $request->filled('animal_id') ? Animal::query()->find($request->integer('animal_id')) : null,
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('stock.show', $stockItem)
            ->with('status', 'Stock updated: now '.$stockItem->formattedQuantity().'.');
    }
}
