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

<label for="sort_order">Sort order</label>
<input id="sort_order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $definition->sort_order ?? 0) }}">

<label class="check" for="active">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $definition->active ?? true))>
    Active (shown on animal forms)
</label>
