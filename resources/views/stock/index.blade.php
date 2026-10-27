@extends('layouts.app')

@section('title', 'Stock · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Stock</h1>
            <p class="hint">Food, medical and other supplies. Items at or below their reorder level show as low stock on the dashboard.</p>
        </div>
        @if (auth()->user()?->role?->canManageStock())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('stock.create') }}">Add stock item</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('stock.index') }}"@if ($lowOnly) data-active-filter="low"@endif>
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                @foreach ($categories as $option)
                    <option value="{{ $option->value }}" @selected($category === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="check" for="filter">
                <input id="filter" name="filter" type="checkbox" value="low" @checked($lowOnly)>
                Low stock only
            </label>
            <button type="submit">Search</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Category</th>
                <th>In stock</th>
                <th>Reorder at</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>
                        <a href="{{ route('stock.show', $item) }}">{{ $item->name }}</a>
                        @if ($item->isLow()) <x-badge tone="quarantine">Low</x-badge>@endif
                        @unless ($item->active) <x-badge>Inactive</x-badge>@endunless
                    </td>
                    <td>{{ $item->category->label() }}</td>
                    <td @class(['quantity-negative' => bccomp((string) $item->quantity, '0', 2) < 0])>{{ $item->formattedQuantity() }}</td>
                    <td>{{ $item->reorder_level !== null ? \App\Models\StockItem::trimDecimal((string) $item->reorder_level).' '.$item->unit : '—' }}</td>
                    <td>{{ $item->location ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No stock items match.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $items->links() }}
@endsection
