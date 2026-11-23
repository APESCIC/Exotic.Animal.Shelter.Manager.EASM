@php
    use App\Enums\ObservationTypeKind;
@endphp
@extends('layouts.app')

@section('title', 'Bulk observations · '.config('app.name'))

@section('content')
    <h1>Bulk daily observations</h1>
    <p class="hint">Tick each animal you are recording for, then enter values across the row. Only filled cells are saved.</p>

    <form method="get" action="{{ route('observations.bulk') }}" class="card">
        <label for="enclosure_id">Enclosure</label>
        <select id="enclosure_id" name="enclosure_id">
            <option value="">— Choose enclosure —</option>
            @foreach ($enclosures as $enclosure)
                <option value="{{ $enclosure->id }}" @selected((string) $enclosureId === (string) $enclosure->id)>{{ $enclosure->name }}</option>
            @endforeach
        </select>

        <label for="location">Or location (free text)</label>
        <input id="location" type="text" name="location" value="{{ $location }}" maxlength="255" placeholder="Matches animals in care at this location">

        <p><button type="submit" class="button">Load animals</button></p>
    </form>

    @if ($animals->isEmpty())
        <p class="hint">Choose an enclosure or location to load animals.</p>
    @else
        <form method="post" action="{{ route('observations.bulk.store') }}" data-dirty-guard>
            @csrf
            <input type="hidden" name="enclosure_id" value="{{ $enclosureId }}">
            <input type="hidden" name="location" value="{{ $location }}">

            <label for="observed_on">Date for all entries</label>
            <input id="observed_on" type="date" name="observed_on" value="{{ $observedOn }}" required>

            <div class="bulk-obs-scroll">
                <table class="bulk-obs-table">
                    <thead>
                        <tr>
                            <th>Record</th>
                            <th>Animal</th>
                            @foreach ($columnTypes as $type)
                                <th>{{ $type->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($animals as $animal)
                            @php
                                $rowTypes = \App\Models\ObservationType::activeForSpecies($animal->species);
                            @endphp
                            <tr>
                                <td>
                                    <label class="check">
                                        <input type="checkbox" name="rows[{{ $animal->id }}][include]" value="1">
                                        Include
                                    </label>
                                </td>
                                <td>
                                    <strong>{{ $animal->name }}</strong>
                                    <span class="hint">{{ $animal->species }}</span>
                                </td>
                                @foreach ($columnTypes as $type)
                                    <td>
                                        @if ($rowTypes->contains('id', $type->id))
                                            @if ($type->kind === ObservationTypeKind::ValueList)
                                                <select name="rows[{{ $animal->id }}][types][{{ $type->id }}]" aria-label="{{ $type->name }} for {{ $animal->name }}">
                                                    <option value="">—</option>
                                                    @foreach ($type->lookupList?->activeValues ?? [] as $value)
                                                        <option value="{{ $value->id }}">{{ $value->name }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" name="rows[{{ $animal->id }}][types][{{ $type->id }}]" maxlength="500" aria-label="{{ $type->name }} for {{ $animal->name }}">
                                            @endif
                                        @else
                                            <span class="hint">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p><button type="submit" class="button-primary">Save observations</button></p>
        </form>
    @endif

    <p><a href="{{ route('animals.index') }}">All animals</a></p>
@endsection