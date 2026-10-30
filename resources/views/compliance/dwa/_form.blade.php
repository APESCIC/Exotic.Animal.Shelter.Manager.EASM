<label for="licence_number">Licence number</label>
<input id="licence_number" type="text" name="licence_number" value="{{ old('licence_number', $licence->licence_number) }}" required maxlength="255">

<label for="issuing_authority">Issuing council</label>
<input id="issuing_authority" type="text" name="issuing_authority" value="{{ old('issuing_authority', $licence->issuing_authority) }}" required maxlength="255" placeholder="Bristol City Council">

<label for="species_covered">Species covered</label>
<textarea id="species_covered" name="species_covered" maxlength="2000">{{ old('species_covered', $licence->species_covered) }}</textarea>

<label for="conditions">Conditions</label>
<textarea id="conditions" name="conditions" maxlength="10000" rows="6">{{ old('conditions', $licence->conditions) }}</textarea>
<p class="hint">Copy the conditions from the licence so staff can check them.</p>

<label for="issued_on">Issued on</label>
<input id="issued_on" type="date" name="issued_on" value="{{ old('issued_on', optional($licence->issued_on)->format('Y-m-d')) }}">

<label for="expires_on">Expires on</label>
<input id="expires_on" type="date" name="expires_on" value="{{ old('expires_on', optional($licence->expires_on)->format('Y-m-d')) }}" required>
<p class="hint">EASM adds a renewal task to the diary {{ \App\Models\DwaLicence::RENEWAL_REMINDER_DAYS }} days before this date. When the licence is renewed, change this date.</p>

<label for="animal_ids">Animals covered</label>
<select id="animal_ids" name="animal_ids[]" multiple size="8">
    @php($selected = array_map('strval', old('animal_ids', $linkedIds)))
    @foreach ($animals as $animal)
        <option value="{{ $animal->id }}" @selected(in_array((string) $animal->id, $selected, true))>{{ $animal->name }} ({{ $animal->species }})</option>
    @endforeach
</select>
<p class="hint">Hold Ctrl (or Cmd) to choose more than one. Lists animals in care and any already on this licence.</p>

<label for="licence_notes">Notes</label>
<textarea id="licence_notes" name="notes" maxlength="5000">{{ old('notes', $licence->notes) }}</textarea>
