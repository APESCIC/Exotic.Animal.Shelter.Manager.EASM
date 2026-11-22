<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $value->name) }}" required maxlength="255" autocomplete="off">

<label for="description">Description (optional)</label>
<input id="description" type="text" name="description" value="{{ old('description', $value->description) }}" maxlength="255">

<label for="sort_order">Display order</label>
<input id="sort_order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $value->sort_order ?? 0) }}">

<label class="check" for="active">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $value->active ?? true))>
    Active (shown on new forms). Uncheck to retire — existing records keep this value.
</label>
