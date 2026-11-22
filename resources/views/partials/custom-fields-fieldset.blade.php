@php
    use App\Enums\CustomFieldType;
    use App\Support\LookupField;
    $definitions = $customFieldDefinitions ?? collect();
    $owner = $owner ?? null;
    $valueMap = $owner && $owner->relationLoaded('customFieldValues')
        ? $owner->customFieldValues->keyBy('definition_id')
        : collect();
@endphp
@if ($definitions->isNotEmpty())
    <fieldset>
        <legend>Custom fields</legend>
        @foreach ($definitions as $definition)
            @php
                $fieldName = 'custom_fields['.$definition->id.']';
                $stored = $valueMap->get($definition->id)?->value;
                $current = old('custom_fields.'.$definition->id, $stored);
                $inputId = 'custom_field_'.$definition->id;
                $hintId = $inputId.'_hint';
                $required = $definition->is_mandatory;
            @endphp
            @if ($definition->type === CustomFieldType::Boolean)
                <label class="check" for="{{ $inputId }}">
                    <input id="{{ $inputId }}" name="{{ $fieldName }}" type="checkbox" value="1" @checked((string) $current === '1') @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
                    {{ $definition->label }}@if ($required) * @endif
                </label>
            @elseif ($definition->type === CustomFieldType::Number)
                <label for="{{ $inputId }}">{{ $definition->label }}@if ($required) * @endif</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="number" step="any" value="{{ $current }}" @if ($required) required @endif @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
            @elseif ($definition->type === CustomFieldType::Date)
                <label for="{{ $inputId }}">{{ $definition->label }}@if ($required) * @endif</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="date" value="{{ $current }}" @if ($required) required @endif @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
            @elseif ($definition->type === CustomFieldType::Lookup)
                @php
                    $options = LookupField::options($definition->lookupList?->key ?? '', is_string($current) ? $current : null);
                @endphp
                <label for="{{ $inputId }}">{{ $definition->label }}@if ($required) * @endif</label>
                <select id="{{ $inputId }}" name="{{ $fieldName }}" @if ($required) required @endif @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
                    <option value="">— Not set —</option>
                    @foreach ($options as $option)
                        <option value="{{ $option->name }}" @selected((string) $current === $option->name)>
                            {{ $option->name }}@if (! $option->active) (retired)@endif
                        </option>
                    @endforeach
                </select>
            @elseif ($definition->type === CustomFieldType::MultiLookup)
                @php
                    $selected = old('custom_fields.'.$definition->id, $definition->decodedMultiLookup($stored));
                    if (! is_array($selected)) {
                        $selected = $selected ? [$selected] : [];
                    }
                    $options = LookupField::options($definition->lookupList?->key ?? '', $selected);
                @endphp
                <label for="{{ $inputId }}">{{ $definition->label }}@if ($required) * @endif</label>
                <select id="{{ $inputId }}" name="{{ $fieldName }}[]" multiple size="4" @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
                    @foreach ($options as $option)
                        <option value="{{ $option->name }}" @selected(in_array($option->name, $selected, true))>
                            {{ $option->name }}@if (! $option->active) (retired)@endif
                        </option>
                    @endforeach
                </select>
            @else
                <label for="{{ $inputId }}">{{ $definition->label }}@if ($required) * @endif</label>
                <input id="{{ $inputId }}" name="{{ $fieldName }}" type="text" value="{{ $current }}" @if ($required) required @endif @if ($definition->tooltip) aria-describedby="{{ $hintId }}" @endif>
            @endif
            @if ($definition->tooltip)
                <p id="{{ $hintId }}" class="hint">{{ $definition->tooltip }}</p>
            @endif
        @endforeach
    </fieldset>
@endif
