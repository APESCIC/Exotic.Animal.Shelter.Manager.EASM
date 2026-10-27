@php
    use App\Models\StockItem;
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', $item->name.' · Stock · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $item->name }}
                @if ($item->isLow()) <x-badge tone="quarantine">Low stock</x-badge>@endif
                @unless ($item->active) <x-badge>Inactive</x-badge>@endunless
            </h1>
            <p class="hint">{{ $item->category->label() }}@if ($item->location) · {{ $item->location }}@endif</p>
        </div>
        @if (auth()->user()?->role?->canManageStock())
            <div class="page-actions">
                <a class="button" href="{{ route('stock.edit', $item) }}">Edit</a>
            </div>
        @endif
    </div>

    <div class="card-grid">
        <section class="card">
            <h2>In stock</h2>
            <p @class(['stat', 'quantity-negative' => bccomp((string) $item->quantity, '0', 2) < 0])>{{ $item->formattedQuantity() }}</p>
            @if (bccomp((string) $item->quantity, '0', 2) < 0)
                <p class="hint">Below zero: record a stocktake count to correct it.</p>
            @endif
        </section>
        <section class="card">
            <h2>Reorder at</h2>
            <p class="stat">{{ $item->reorder_level !== null ? StockItem::trimDecimal((string) $item->reorder_level).' '.$item->unit : 'No alert' }}</p>
        </section>
    </div>

    @if ($item->notes)
        <p>{{ $item->notes }}</p>
    @endif

    @if (auth()->user()?->role?->canRecordStockMovements())
        <section class="card">
            <h2>Record a change</h2>
            <form method="post" action="{{ route('stock.movements.store', $item) }}">
                @csrf
                <label for="reason">What happened</label>
                <select id="reason" name="reason" required>
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->value }}" @selected(old('reason', 'used') === $reason->value)>{{ $reason->label() }} ({{ strtolower($reason->quantityHint()) }})</option>
                    @endforeach
                </select>

                <label for="quantity">Quantity ({{ $item->unit }})</label>
                <input id="quantity" type="text" name="quantity" inputmode="decimal" value="{{ old('quantity') }}" required maxlength="12">

                <label for="animal_id">For animal (optional)</label>
                <select id="animal_id" name="animal_id">
                    <option value="">— None —</option>
                    @foreach ($animals as $animal)
                        <option value="{{ $animal->id }}" @selected((string) old('animal_id') === (string) $animal->id)>{{ $animal->name }} ({{ $animal->species }})</option>
                    @endforeach
                </select>

                <label for="notes">Notes</label>
                <input id="notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="1000">

                <p><button type="submit" class="button-primary">Save</button></p>
            </form>
        </section>
    @endif

    <h2>History</h2>
    @if ($movements->isEmpty())
        <p class="hint">No changes recorded yet.</p>
    @else
        <table>
            <thead>
                <tr><th>When</th><th>What</th><th>Change</th><th>After</th><th>Animal</th><th>By</th><th>Notes</th></tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ UkDate::format($movement->created_at) }}</td>
                        <td>{{ $movement->reason->label() }}</td>
                        <td>{{ bccomp((string) $movement->change, '0', 2) > 0 ? '+' : '' }}{{ StockItem::trimDecimal((string) $movement->change) }}</td>
                        <td>{{ StockItem::trimDecimal((string) $movement->quantity_after) }}</td>
                        <td>
                            @if ($movement->animal)
                                <a href="{{ \App\Enums\AnimalTab::Health->url($movement->animal) }}">{{ $movement->animal->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $movement->user?->name ?? '—' }}</td>
                        <td>{{ $movement->notes ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $movements->links() }}
    @endif
@endsection
