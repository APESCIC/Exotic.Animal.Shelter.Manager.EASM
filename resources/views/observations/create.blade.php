@php
    use App\Enums\ObservationTypeKind;
@endphp
@extends('layouts.app')

@section('title', 'Add observation · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Add observation</h1>
    <p class="hint"><a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a> · {{ $animal->species }}</p>

    <form method="post" action="{{ route('animals.observations.store', $animal) }}" data-dirty-guard>
        @csrf

        <label for="observed_on">Date</label>
        <input id="observed_on" type="date" name="observed_on" value="{{ old('observed_on', $observation->observed_on instanceof \Carbon\CarbonInterface ? $observation->observed_on->format('Y-m-d') : $observation->observed_on) }}" required>

        @if ($types->isNotEmpty())
            <label for="observation_type_id">Observation type</label>
            <select id="observation_type_id" name="observation_type_id">
                <option value="">Legacy free-text note only</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) old('observation_type_id') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            @foreach ($types as $type)
                <div class="obs-type-fields" data-type-id="{{ $type->id }}" @if ((string) old('observation_type_id') !== (string) $type->id) hidden @endif>
                    @if ($type->kind === ObservationTypeKind::ValueList)
                        <label for="lookup_value_id_{{ $type->id }}">{{ $type->name }}</label>
                        <select id="lookup_value_id_{{ $type->id }}" name="lookup_value_id" @disabled((string) old('observation_type_id', '') !== (string) $type->id)>
                            <option value="">Choose…</option>
                            @foreach ($type->lookupList?->activeValues ?? [] as $value)
                                <option value="{{ $value->id }}" @selected((string) old('lookup_value_id') === (string) $value->id)>
                                    {{ $value->name }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <label for="value_text_{{ $type->id }}">{{ $type->name }}</label>
                        <input id="value_text_{{ $type->id }}" type="text" name="value_text" value="{{ old('value_text') }}" maxlength="500" @disabled((string) old('observation_type_id', '') !== (string) $type->id)>
                    @endif
                </div>
            @endforeach

            <label for="body">Notes (optional)</label>
            <textarea id="body" name="body" maxlength="5000">{{ old('body', $observation->body) }}</textarea>
        @else
            <label for="body">What did you observe?</label>
            <textarea id="body" name="body" required maxlength="5000">{{ old('body', $observation->body) }}</textarea>
        @endif

        <p>
            <button type="submit">Save observation</button>
            <a class="button" href="{{ route('animals.show', [$animal, 'log']) }}">Cancel</a>
        </p>
    </form>

    @if ($types->isNotEmpty())
        <script>
            (function () {
                const select = document.getElementById('observation_type_id');
                const blocks = document.querySelectorAll('.obs-type-fields');
                function sync() {
                    const id = select.value;
                    blocks.forEach((block) => {
                        const match = block.dataset.typeId === id;
                        block.hidden = !match;
                        block.querySelectorAll('input, select, textarea').forEach((el) => { el.disabled = !match; });
                    });
                }
                select?.addEventListener('change', sync);
                sync();
            })();
        </script>
    @endif
@endsection