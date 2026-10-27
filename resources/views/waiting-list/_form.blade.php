<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $entry->type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</select>

<label for="urgency">Urgency</label>
<select id="urgency" name="urgency" required>
    @foreach ($urgencies as $urgency)
        <option value="{{ $urgency->value }}" @selected(old('urgency', $entry->urgency?->value) === $urgency->value)>{{ $urgency->label() }}</option>
    @endforeach
</select>
<p class="hint">Urgent: animal welfare at risk or a deadline (eviction, owner in hospital). High: needs a place within weeks.</p>

<label for="species">Species</label>
<input id="species" type="text" name="species" value="{{ old('species', $entry->species) }}" required maxlength="255" placeholder="Free text, e.g. Burmese python, Sulcata tortoise">

<label for="person_id">Contact</label>
<select id="person_id" name="person_id">
    <option value="">— Not in People yet —</option>
    @foreach ($people as $person)
        <option value="{{ $person->id }}" @selected((string) old('person_id', $entry->person_id) === (string) $person->id)>{{ $person->name }}</option>
    @endforeach
</select>

<fieldset class="fieldset">
    <legend>Or contact details</legend>
    <label for="contact_name">Name</label>
    <input id="contact_name" type="text" name="contact_name" value="{{ old('contact_name', $entry->contact_name) }}" maxlength="255">
    <label for="contact_phone">Phone</label>
    <input id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone', $entry->contact_phone) }}" maxlength="64">
    <label for="contact_email">Email</label>
    <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $entry->contact_email) }}" maxlength="255">
</fieldset>

<label for="details">Details</label>
<textarea id="details" name="details" maxlength="5000" placeholder="Number of animals, size, set-up, health, licences">{{ old('details', $entry->details) }}</textarea>

<label for="added_on">Date added</label>
<input id="added_on" type="date" name="added_on" value="{{ old('added_on', $entry->added_on?->format('Y-m-d')) }}" required>
