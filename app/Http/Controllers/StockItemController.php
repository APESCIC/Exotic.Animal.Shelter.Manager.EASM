<?php

namespace App\Http\Controllers;

use App\Enums\StockCategory;
use App\Enums\StockMovementReason;
use App\Http\Requests\StoreStockItemRequest;
use App\Models\Animal;
use App\Models\StockItem;
use App\Services\StockLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockItemController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function index(Request $request): View
    {
        $category = StockCategory::tryFrom($request->string('category')->toString());
        $lowOnly = $request->string('filter')->toString() === 'low';
        $q = trim($request->string('q')->toString());

        $items = StockItem::query()
            ->when($lowOnly, fn ($query) => $query->lowStock())
            ->when($category !== null, fn ($query) => $query->where('category', $category->value))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('stock.index', [
            'items' => $items,
            'categories' => StockCategory::cases(),
            'category' => $category,
            'lowOnly' => $lowOnly,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('stock.create', [
            'item' => new StockItem(['category' => StockCategory::Food, 'active' => true]),
            'categories' => StockCategory::cases(),
        ]);
    }

    public function store(StoreStockItemRequest $request): RedirectResponse
    {
        $item = StockItem::query()->create([...$request->itemData(), 'quantity' => '0']);

        if ($request->filled('opening_quantity')) {
            $this->ledger->record(
                $item,
                StockMovementReason::Counted,
                $request->string('opening_quantity')->toString(),
                $request->user(),
                notes: 'Opening stock',
            );
        }

        return redirect()
            ->route('stock.show', $item)
            ->with('status', 'Stock item added.');
    }

    public function show(StockItem $stockItem): View
    {
        return view('stock.show', [
            'item' => $stockItem,
            'movements' => $stockItem->movements()->with(['animal', 'medicalRecord', 'user'])->paginate(30),
            'reasons' => StockMovementReason::cases(),
            'animals' => Animal::query()->orderBy('name')->get(['id', 'name', 'species']),
        ]);
    }

    public function edit(StockItem $stockItem): View
    {
        $this->authorizeManage();

        return view('stock.edit', [
            'item' => $stockItem,
            'categories' => StockCategory::cases(),
        ]);
    }

    public function update(StoreStockItemRequest $request, StockItem $stockItem): RedirectResponse
    {
        $stockItem->update($request->itemData());

        return redirect()
            ->route('stock.show', $stockItem)
            ->with('status', 'Stock item updated.');
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageStock(), 403);
    }
}
