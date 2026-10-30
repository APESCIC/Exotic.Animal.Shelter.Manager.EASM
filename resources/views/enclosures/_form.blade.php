<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $enclosure->name) }}" required maxlength="255" placeholder="Viv 3, Flight A, Tortoise pen 2">

<label for="zone">Zone</label>
<input id="zone" type="text" name="zone" value="{{ old('zone', $enclosure->zone) }}" maxlength="255" placeholder="Reptile House, Aviary, Quarantine">

<label for="size">Size</label>
<input id="size" type="text" name="size" value="{{ old('size', $enclosure->size) }}" maxlength="255" placeholder="120 x 60 x 60 cm">

<label for="capacity">Capacity (animals)</label>
<input id="capacity" type="number" name="capacity" min="1" max="1000" value="{{ old('capacity', $enclosure->capacity) }}">
<p class="hint">EASM warns when more animals in care are placed here. Leave blank for no limit.</p>

<label for="notes">Notes</label>
<textarea id="notes" name="notes" maxlength="5000">{{ old('notes', $enclosure->notes) }}</textarea>

<label class="check" for="active">
    <input type="hidden" name="active" value="0">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $enclosure->active ?? true))>
    In use (untick for enclosures you have retired)
</label>
