<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $item->name) }}" required maxlength="255" placeholder="Live crickets (large), Baytril 2.5%">

<label for="category">Category</label>
<select id="category" name="category" required>
    @foreach ($categories as $category)
        <option value="{{ $category->value }}" @selected(old('category', $item->category?->value) === $category->value)>{{ $category->label() }}</option>
    @endforeach
</select>

<label for="unit">Unit</label>
<input id="unit" type="text" name="unit" value="{{ old('unit', $item->unit) }}" required maxlength="32" placeholder="tubs, kg, ml, doses">

@unless ($item->exists)
    <label for="opening_quantity">Quantity in stock now</label>
    <input id="opening_quantity" type="text" name="opening_quantity" inputmode="decimal" value="{{ old('opening_quantity') }}" maxlength="12">
@endunless

<label for="reorder_level">Reorder level</label>
<input id="reorder_level" type="text" name="reorder_level" inputmode="decimal" value="{{ old('reorder_level', $item->reorder_level !== null ? \App\Models\StockItem::trimDecimal((string) $item->reorder_level) : '') }}" maxlength="12">
<p class="hint">Show a low-stock alert when the quantity falls to this level or below. Leave blank for no alert.</p>

<label for="location">Location</label>
<input id="location" type="text" name="location" value="{{ old('location', $item->location) }}" maxlength="255" placeholder="Food store, medicine fridge">

<label for="notes">Notes</label>
<textarea id="notes" name="notes" maxlength="5000">{{ old('notes', $item->notes) }}</textarea>

<label class="check" for="active">
    <input type="hidden" name="active" value="0">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $item->active ?? true))>
    Active (untick for items you no longer stock)
</label>
