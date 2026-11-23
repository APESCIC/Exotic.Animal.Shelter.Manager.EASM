@php
    use App\Enums\ObservationTypeKind;
@endphp
@extends('layouts.app')

@section('title', 'Edit observation · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Edit observation</h1>
    <p class="hint"><a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a></p>

    <form method="post" action="{{ route('animals.observations.update', [$animal, $observation]) }}" data-dirty-guard>
        @csrf
        @method('PUT')

        <label for="observed_on">Date</label>
        <input id="observed_on" type="date" name="observed_on" value="{{ old('observed_on', $observation->observed_on?->format('Y-m-d')) }}" required>

        @if ($types->isNotEmpty() && $observation->observation_type_id)
            <label for="observation_type_id">Observation type</label>
            <select id="observation_type_id" name="observation_type_id" required>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) old('observation_type_id', $observation->observation_type_id) === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>

            @php
                $activeType = $types->firstWhere('id', (int) old('observation_type_id', $observation->observation_type_id));
            @endphp
            @if ($activeType?->kind === ObservationTypeKind::ValueList)
                <label for="lookup_value_id">Value</label>
                <select id="lookup_value_id" name="lookup_value_id" required>
                    @foreach ($activeType->lookupList?->activeValues ?? [] as $value)
                        <option value="{{ $value->id }}" @selected((string) old('lookup_value_id', $observation->lookup_value_id) === (string) $value->id)>
                            {{ $value->name }}
                        </option>
                    @endforeach
                </select>
            @elseif ($activeType)
                <label for="value_text">Value</label>
                <input id="value_text" type="text" name="value_text" value="{{ old('value_text', $observation->value_text) }}" maxlength="500" required>
            @endif

            <label for="body">Notes (optional)</label>
            <textarea id="body" name="body" maxlength="5000">{{ old('body', $observation->body) }}</textarea>
        @else
            <label for="body">What did you observe?</label>
            <textarea id="body" name="body" required maxlength="5000">{{ old('body', $observation->body) }}</textarea>
        @endif

        <p>
            <button type="submit">Save changes</button>
            <a class="button" href="{{ route('animals.show', [$animal, 'log']) }}">Cancel</a>
        </p>
    </form>
@endsection