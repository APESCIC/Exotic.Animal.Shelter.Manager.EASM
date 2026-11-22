<fieldset>
    <legend>Case</legend>
    @php
        $reportedAtValue = old('reported_at');
        if ($reportedAtValue === null) {
            $reportedAtValue = ($case->reported_at ?? now())->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
        }
    @endphp
    <label for="reported_at">Reported at</label>
    <input id="reported_at" name="reported_at" type="datetime-local" value="{{ $reportedAtValue }}" required>

    <label for="location_area">Location / area</label>
    <input id="location_area" name="location_area" type="text" value="{{ old('location_area', $case->location_area) }}" required>

    <label for="species">Species</label>
    <input id="species" name="species" type="text" value="{{ old('species', $case->species) }}" placeholder="Free-text exotic species">

    <label for="summary">Summary</label>
    <input id="summary" name="summary" type="text" value="{{ old('summary', $case->summary) }}" required maxlength="255">

    <label for="notes">Notes</label>
    <textarea id="notes" name="notes">{{ old('notes', $case->notes) }}</textarea>
</fieldset>

<fieldset>
    <legend>Links</legend>
    <label for="reporter_person_id">Reporter / contact</label>
    <select id="reporter_person_id" name="reporter_person_id">
        <option value="">None</option>
        @foreach ($people as $person)
            <option value="{{ $person->id }}" @selected((string) old('reporter_person_id', $case->reporter_person_id) === (string) $person->id)>{{ $person->name }}</option>
        @endforeach
    </select>

    <label for="linked_animal_id">Linked animal</label>
    <select id="linked_animal_id" name="linked_animal_id">
        <option value="">None</option>
        @foreach ($animals as $animal)
            <option value="{{ $animal->id }}" @selected((string) old('linked_animal_id', $case->linked_animal_id) === (string) $animal->id)>{{ $animal->name }} ({{ $animal->species }})</option>
        @endforeach
    </select>
</fieldset>
