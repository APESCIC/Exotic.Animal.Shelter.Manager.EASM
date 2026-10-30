@extends('layouts.app')

@section('title', 'Animals · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Animals</h1>
            <p class="hint">Exotic species stay free-text. Search by name, species, code, location, enclosure, CITES, or DWA.</p>
        </div>
        @if (auth()->user()?->role?->canManageAnimals())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('animals.create') }}">Add animal</a>
            </div>
        @endif
    </div>

    @if ($filter)
        <p class="filter-chips">
            <span class="chip" data-active-filter="{{ $filter->value }}">
                {{ $filter->label() }}
                <a href="{{ route('animals.index', array_filter(['q' => $q, 'location' => $location])) }}" aria-label="Remove filter: {{ $filter->label() }}">Remove</a>
            </span>
        </p>
    @endif

    <form class="filters" method="get" action="{{ route('animals.index') }}">
        @if ($filter)
            <input type="hidden" name="filter" value="{{ $filter->value }}">
        @endif
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="location">Location</label>
            <select id="location" name="location">
                <option value="">All locations</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc }}" @selected($location === $loc)>{{ $loc }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Search</button>
    </form>

    <table>
        <thead>
            <tr>
                <th><span class="visually-hidden">Photo</span></th>
                <th>Name</th>
                <th>Species</th>
                <th>Status</th>
                <th>Location</th>
                <th>Enclosure</th>
                <th>CITES</th>
                <th>DWA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($animals as $animal)
                <tr>
                    <td>
                        @if ($animal->primaryPhotoUrl())
                            <img class="thumb" src="{{ $animal->primaryPhotoUrl() }}" alt="">
                        @endif
                    </td>
                    <td><a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a></td>
                    <td>{{ $animal->species }}</td>
                    <td>@include('animals._badges')</td>
                    <td>{{ $animal->location }}</td>
                    <td>
                        @if ($animal->enclosure)
                            <a href="{{ route('enclosures.show', $animal->enclosure) }}">{{ $animal->enclosure->name }}</a>
                        @endif
                    </td>
                    <td>{{ $animal->cites }}</td>
                    <td>{{ $animal->dwa }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No animals match.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $animals->links() }}
@endsection
