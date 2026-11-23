@php
    use App\Enums\ObservationTypeKind;
@endphp

<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $type->name) }}" required maxlength="255">

<label for="kind">Kind</label>
<select id="kind" name="kind" required>
    @foreach ($kinds as $kind)
        <option value="{{ $kind->value }}" @selected(old('kind', $type->kind?->value) === $kind->value)>
            {{ $kind->label() }}
        </option>
    @endforeach
</select>

<label for="lookup_list_id">Lookup list (for value list types)</label>
<select id="lookup_list_id" name="lookup_list_id">
    <option value="">— None —</option>
    @foreach ($lookupLists as $list)
        <option value="{{ $list->id }}" @selected((string) old('lookup_list_id', $type->lookup_list_id) === (string) $list->id)>
            {{ $list->name }}
        </option>
    @endforeach
</select>

<label for="sort_order">Sort order</label>
<input id="sort_order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $type->sort_order ?? 0) }}">

<label for="species_filter">Species filter (optional)</label>
<textarea id="species_filter" name="species_filter" rows="4" maxlength="5000" placeholder="One species per line, matched case-insensitively against the animal record">{{ old('species_filter', $type->speciesFilterDisplay()) }}</textarea>
<p class="hint">Leave blank to show this type for every species.</p>

<label class="check" for="active">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $type->active ?? true))>
    Active (shown on forms). Uncheck to retire — past observations keep their type.
</label>