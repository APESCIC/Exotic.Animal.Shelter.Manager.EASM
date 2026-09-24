@php
    use App\Enums\AnimalSex;
@endphp

<fieldset>
    <legend>Identity</legend>
    <label for="name">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $animal->name) }}" required>

    <label for="species">Species (free text)</label>
    <input id="species" name="species" type="text" value="{{ old('species', $animal->species) }}" required placeholder="e.g. Bearded dragon">

    <label for="breed_type">Breed / type</label>
    <input id="breed_type" name="breed_type" type="text" value="{{ old('breed_type', $animal->breed_type) }}">

    <label for="sex">Sex</label>
    <select id="sex" name="sex" required>
        @foreach (AnimalSex::cases() as $sex)
            <option value="{{ $sex->value }}" @selected(old('sex', $animal->sex?->value ?? 'unknown') === $sex->value)>{{ $sex->label() }}</option>
        @endforeach
    </select>

    <label for="date_of_birth">Date of birth</label>
    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', optional($animal->date_of_birth)->format('Y-m-d')) }}">

    <label for="age_years">Age (years)</label>
    <input id="age_years" name="age_years" type="number" min="0" max="200" value="{{ old('age_years', $animal->age_years) }}">

    <label for="colour">Colour</label>
    <input id="colour" name="colour" type="text" value="{{ old('colour', $animal->colour) }}">

    <label for="identifying_code">Identifying code</label>
    <input id="identifying_code" name="identifying_code" type="text" value="{{ old('identifying_code', $animal->identifying_code) }}">
</fieldset>

<fieldset>
    <legend>Care and location</legend>
    <label for="location">Location / unit</label>
    <input id="location" name="location" type="text" value="{{ old('location', $animal->location) }}">

    <label for="enclosure">Enclosure</label>
    <input id="enclosure" name="enclosure" type="text" value="{{ old('enclosure', $animal->enclosure) }}">

    <label for="cites">CITES</label>
    <input id="cites" name="cites" type="text" value="{{ old('cites', $animal->cites) }}">

    <label for="dwa">DWA</label>
    <input id="dwa" name="dwa" type="text" value="{{ old('dwa', $animal->dwa) }}">

    <label for="bonded_animals">Bonded animals</label>
    <input id="bonded_animals" name="bonded_animals" type="text" value="{{ old('bonded_animals', $animal->bonded_animals) }}" placeholder="Names or codes of bonded animals">

    <label for="entry_reason">Entry reason</label>
    <input id="entry_reason" name="entry_reason" type="text" value="{{ old('entry_reason', $animal->entry_reason) }}">

    <label for="flags">Flags</label>
    <input id="flags" name="flags" type="text" value="{{ old('flags', $animal->flags) }}">

    <label class="check" for="non_shelter">
        <input id="non_shelter" name="non_shelter" type="checkbox" value="1" @checked(old('non_shelter', $animal->non_shelter))>
        Non-shelter animal
    </label>
</fieldset>

<fieldset>
    <legend>Death (if applicable)</legend>
    <label for="deceased_at">Date of death</label>
    <input id="deceased_at" name="deceased_at" type="date" value="{{ old('deceased_at', optional($animal->deceased_at)->format('Y-m-d')) }}">

    <label for="death_reason">Death reason</label>
    <input id="death_reason" name="death_reason" type="text" value="{{ old('death_reason', $animal->death_reason) }}">
</fieldset>

<fieldset>
    <legend>Primary photo</legend>
    @if ($animal->primaryPhotoUrl())
        <p><img class="photo" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}"></p>
    @endif
    <label for="primary_photo">{{ $animal->exists ? 'Replace primary photo' : 'Primary photo' }}</label>
    <input id="primary_photo" name="primary_photo" type="file" accept="image/*">
</fieldset>

@php
    use App\Enums\CustomFieldType;
    $definitions = $customFieldDefinitions ?? collect();
    $valueMap = $animal->relationLoaded('customFieldValues')
        ? $animal->customFieldValues->keyBy('definition_id')
        : collect();
@endphp
@if ($definitions->isNotEmpty())
    <fieldset>
        <legend>Custom fields</legend>
        @foreach ($definitions as $definition)
            @php
                $fieldName = 'custom_fields['.$definition->id.']';
                $current = old('custom_fields.'.$definition->id, $valueMap->get($definition->id)?->value);
                $inputId = 'custom_field_'.$definition->id;
            @endphp
            @if ($definition->type === CustomFieldType::Boolean)
                <label class="check" for="{{ $inputId }}">
                    <input id="{{ $inputId }}" name="{{ $fieldName }}" type="checkbox" value="1" @checked((string) $current === '1')>
                    {{ $definition->label }}
                </label>
            @elseif ($definition->type === CustomFieldType::Number)
                <label for="{{ $inputId }}">{{ $definition->label }}</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="number" step="any" value="{{ $current }}">
            @elseif ($definition->type === CustomFieldType::Date)
                <label for="{{ $inputId }}">{{ $definition->label }}</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="date" value="{{ $current }}">
            @else
                <label for="{{ $inputId }}">{{ $definition->label }}</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="text" value="{{ $current }}">
            @endif
        @endforeach
    </fieldset>
@endif
