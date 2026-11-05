<label for="user_id">Person</label>
<select id="user_id" name="user_id">
    <option value="">Open shift (nobody yet)</option>
    @foreach ($people as $person)
        <option value="{{ $person->id }}" @selected((string) old('user_id', $shift->user_id) === (string) $person->id)>{{ $person->name }} ({{ $person->role->label() }})</option>
    @endforeach
</select>

<label for="date">Date</label>
<input id="date" type="date" name="date" value="{{ old('date', $shift->starts_at?->toDateString()) }}" required>

<label for="start_time">Starts</label>
<input id="start_time" type="time" name="start_time" value="{{ old('start_time', $shift->starts_at?->format('H:i')) }}" required>

<label for="end_time">Ends</label>
<input id="end_time" type="time" name="end_time" value="{{ old('end_time', $shift->ends_at?->format('H:i')) }}" required>
<p class="hint">An end time earlier than the start means the shift runs past midnight.</p>

<label for="area">Area</label>
<input id="area" type="text" name="area" value="{{ old('area', $shift->area) }}" maxlength="255" placeholder="Reptile house">

<label for="shift_notes">Notes</label>
<textarea id="shift_notes" name="notes" maxlength="2000">{{ old('notes', $shift->notes) }}</textarea>
