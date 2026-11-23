@php
    $role = auth()->user()?->role;
    $typeFilter = request()->integer('observation_type') ?: null;
@endphp
<p class="hint">Daily observations by admin, staff and volunteers.</p>

<form method="get" action="{{ route('animals.show', [$animal, 'log']) }}" class="filter-inline">
    <label for="observation_type">Filter by type</label>
    <select id="observation_type" name="observation_type" onchange="this.form.submit()">
        <option value="">All types</option>
        @foreach ($observationTypeFilters as $filterType)
            <option value="{{ $filterType->id }}" @selected($typeFilter === $filterType->id)>{{ $filterType->name }}</option>
        @endforeach
    </select>
    @if ($typeFilter)
        <span class="hint">Showing the last 30 days for this type.</span>
    @endif
</form>

@if ($animal->observations->isEmpty())
    <p class="hint">No daily observations yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Value</th>
                <th>Notes</th>
                <th>By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->observations as $observation)
                <tr>
                    <td>{{ \App\Support\UkDate::format($observation->observed_on) }}</td>
                    <td>{{ $observation->observationType?->name ?? '—' }}</td>
                    <td>{{ $observation->displayValue() }}</td>
                    <td>{{ $observation->observation_type_id ? ($observation->body ?: '—') : '—' }}</td>
                    <td>{{ $observation->user?->name ?: '—' }}</td>
                    <td>
                        @if ($observation->canBeModifiedBy(auth()->user()))
                            <a href="{{ route('animals.observations.edit', [$animal, $observation]) }}">Edit</a>
                            <form method="post" action="{{ route('animals.observations.destroy', [$animal, $observation]) }}" class="inline-form" data-confirm="Delete this observation?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link-button">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($role?->canRecordObservations())
    <p>
        <a class="button" href="{{ route('animals.observations.create', $animal) }}">Add observation</a>
        <a class="button" href="{{ route('observations.bulk') }}">Bulk entry</a>
    </p>
@endif