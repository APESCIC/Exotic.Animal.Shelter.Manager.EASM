@php
    $dateValue = function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    };
@endphp

<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $record->type?->value) === $type->value)>
            {{ $type->label() }}
        </option>
    @endforeach
</select>

<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $record->name) }}" required maxlength="255" placeholder="Free-text product, test, or treatment">

<label for="due_on">Due</label>
<input id="due_on" type="date" name="due_on" value="{{ old('due_on', $dateValue($record->due_on)) }}">

<label for="given_on">Given</label>
<input id="given_on" type="date" name="given_on" value="{{ old('given_on', $dateValue($record->given_on)) }}">
<p class="hint">Set when the dose or procedure was completed.</p>

<label for="expires_on">Expires</label>
<input id="expires_on" type="date" name="expires_on" value="{{ old('expires_on', $dateValue($record->expires_on)) }}">

<label for="notes">Notes</label>
<textarea id="notes" name="notes" maxlength="5000">{{ old('notes', $record->notes) }}</textarea>

@isset($stockItems)
    @if ($stockItems->isNotEmpty())
        <fieldset class="fieldset">
            <legend>Stock used (optional)</legend>
            <label for="stock_item_id">Stock item</label>
            <select id="stock_item_id" name="stock_item_id">
                <option value="">— None —</option>
                @foreach ($stockItems as $stockItem)
                    <option value="{{ $stockItem->id }}" @selected((string) old('stock_item_id') === (string) $stockItem->id)>
                        {{ $stockItem->name }} ({{ $stockItem->category->label() }}, {{ $stockItem->formattedQuantity() }} left)
                    </option>
                @endforeach
            </select>
            <label for="stock_quantity">Quantity used</label>
            <input id="stock_quantity" type="text" name="stock_quantity" inputmode="decimal" value="{{ old('stock_quantity') }}" maxlength="12">
            <p class="hint">Takes the amount off stock when you save.</p>
        </fieldset>
    @endif
@endisset
