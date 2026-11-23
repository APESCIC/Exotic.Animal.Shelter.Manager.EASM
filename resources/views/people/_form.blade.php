@php
    use App\Enums\PersonCategory;
@endphp

<fieldset>
    <legend>Identity</legend>
    <label for="name">Name</label>
    <input id="name" name="name" type="text" value="{{ old('name', $person->name) }}" required>

    <label for="category">Category</label>
    <select id="category" name="category" required>
        @foreach (PersonCategory::cases() as $category)
            <option value="{{ $category->value }}" @selected(old('category', $person->category?->value ?? 'adopter') === $category->value)>{{ $category->label() }}</option>
        @endforeach
    </select>

    <label for="category_custom">Custom category label</label>
    <input id="category_custom" name="category_custom" type="text" value="{{ old('category_custom', $person->category_custom) }}" placeholder="Required when category is Custom">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $person->email) }}">

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="text" value="{{ old('phone', $person->phone) }}">
</fieldset>

<fieldset>
    <legend>Address</legend>
    <label for="address_line1">Address line 1</label>
    <input id="address_line1" name="address_line1" type="text" value="{{ old('address_line1', $person->address_line1) }}">

    <label for="address_line2">Address line 2</label>
    <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2', $person->address_line2) }}">

    <label for="town_city">Town / city</label>
    <input id="town_city" name="town_city" type="text" value="{{ old('town_city', $person->town_city) }}">

    <label for="county">County</label>
    <input id="county" name="county" type="text" value="{{ old('county', $person->county) }}">

    <label for="postcode">Postcode</label>
    <input id="postcode" name="postcode" type="text" value="{{ old('postcode', $person->postcode) }}">
</fieldset>

<fieldset>
    <legend>Flags and notes</legend>
    <label class="check" for="banned">
        <input id="banned" name="banned" type="checkbox" value="1" @checked(old('banned', $person->banned))>
        Banned
    </label>

    <label class="check" for="homechecked">
        <input id="homechecked" name="homechecked" type="checkbox" value="1" @checked(old('homechecked', $person->homechecked))>
        Homechecked
    </label>

    <label for="flags">Other flags</label>
    <input id="flags" name="flags" type="text" value="{{ old('flags', $person->flags) }}">

    <label for="notes">Notes</label>
    <textarea id="notes" name="notes">{{ old('notes', $person->notes) }}</textarea>
</fieldset>

@include('partials.custom-fields-fieldset', [
    'customFieldDefinitions' => $customFieldDefinitions ?? collect(),
    'owner' => $person,
])
