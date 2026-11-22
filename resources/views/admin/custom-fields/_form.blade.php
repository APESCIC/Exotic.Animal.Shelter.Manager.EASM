@php
    use App\Enums\CustomFieldType;
@endphp

<label for="label">Label</label>
<input id="label" type="text" name="label" value="{{ old('label', $definition->label) }}" required maxlength="255">

<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $definition->type?->value) === $type->value)>
            {{ $type->label() }}
        </option>
    @endforeach
</select>

<label for="lookup_list_id">Lookup list (for lookup types)</label>
<select id="lookup_list_id" name="lookup_list_id">
    <option value="">— None —</option>
    @foreach ($lookupLists as $list)
        <option value="{{ $list->id }}" @selected((string) old('lookup_list_id', $definition->lookup_list_id) === (string) $list->id)>
            {{ $list->name }}
        </option>
    @endforeach
</select>

<label for="sort_order">Sort order</label>
<input id="sort_order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $definition->sort_order ?? 0) }}">

<label for="tooltip">Tooltip / help text</label>
<input id="tooltip" type="text" name="tooltip" value="{{ old('tooltip', $definition->tooltip) }}" maxlength="255">

<label for="species_filter">Species filter (optional)</label>
<input id="species_filter" type="text" name="species_filter" value="{{ old('species_filter', $definition->species_filter) }}" maxlength="255" placeholder="e.g. Bearded dragon">
<p class="hint">When set, the field only appears on animals whose free-text species matches (case-insensitive).</p>

<label class="check" for="is_mandatory">
    <input id="is_mandatory" name="is_mandatory" type="checkbox" value="1" @checked(old('is_mandatory', $definition->is_mandatory ?? false))>
    Mandatory (blocks save when empty)
</label>

<label class="check" for="show_on_new_record">
    <input id="show_on_new_record" name="show_on_new_record" type="checkbox" value="1" @checked(old('show_on_new_record', $definition->show_on_new_record ?? true))>
    Show on new animal form
</label>

<label class="check" for="active">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $definition->active ?? true))>
    Active (shown on forms). Uncheck to retire — saved values stay on the record.
</label>
