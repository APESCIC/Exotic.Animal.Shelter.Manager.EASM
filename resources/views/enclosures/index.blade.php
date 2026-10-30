@extends('layouts.app')

@section('title', 'Enclosures · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Enclosures</h1>
            <p class="hint">Vivaria, flights, pens and rooms. Occupants are the animals in care placed in each enclosure.</p>
        </div>
        <div class="page-actions">
            <a class="button" href="{{ route('animals.shelter', ['group' => 'enclosure']) }}">Shelter view by enclosure</a>
            @if (auth()->user()?->role?->canManageAnimals())
                <a class="button button-primary" href="{{ route('enclosures.create') }}">Add enclosure</a>
            @endif
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('enclosures.index') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="zone">Zone</label>
            <select id="zone" name="zone">
                <option value="">All zones</option>
                @foreach ($zones as $option)
                    <option value="{{ $option }}" @selected($zone === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Search</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Enclosure</th>
                <th>Zone</th>
                <th>Size</th>
                <th>Occupants</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($enclosures as $enclosure)
                <tr>
                    <td><a href="{{ route('enclosures.show', $enclosure) }}">{{ $enclosure->name }}</a></td>
                    <td>{{ $enclosure->zone ?: '—' }}</td>
                    <td>{{ $enclosure->size ?: '—' }}</td>
                    <td>@include('enclosures._occupancy')</td>
                </tr>
            @empty
                <tr><td colspan="4">No enclosures match.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $enclosures->links() }}
@endsection
