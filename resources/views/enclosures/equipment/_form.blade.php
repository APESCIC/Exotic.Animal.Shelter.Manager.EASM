<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $equipment->type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</select>

<label for="equipment_name">Make and model</label>
<input id="equipment_name" type="text" name="name" value="{{ old('name', $equipment->name) }}" required maxlength="255" placeholder="Arcadia T5 12% 54W">

<label for="installed_on">Fitted on</label>
<input id="installed_on" type="date" name="installed_on" value="{{ old('installed_on', optional($equipment->installed_on)->format('Y-m-d')) }}">

<label for="replace_every_days">Replace every (days)</label>
<input id="replace_every_days" type="number" name="replace_every_days" min="1" max="3650" value="{{ old('replace_every_days', $equipment->replace_every_days) }}">
<p class="hint">UVB output fades before the lamp stops lighting; follow the manufacturer's replacement interval. EASM adds a diary task when replacement is due. Leave blank for no reminder.</p>

<label for="equipment_notes">Notes</label>
<textarea id="equipment_notes" name="notes" maxlength="2000">{{ old('notes', $equipment->notes) }}</textarea>

<label class="check" for="equipment_active">
    <input type="hidden" name="active" value="0">
    <input id="equipment_active" name="active" type="checkbox" value="1" @checked(old('active', $equipment->active ?? true))>
    In use (untick when removed; no more reminders)
</label>
