@extends('layouts.app')

@section('title', 'Shelter view · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Shelter view</h1>
            <p class="hint">
                @if ($byEnclosure)
                    Animals in care grouped by enclosure, with occupancy against capacity.
                @else
                    Animals grouped by location / unit.
                @endif
            </p>
        </div>
        <div class="page-actions">
            @if ($byEnclosure)
                <a class="button" href="{{ route('animals.shelter') }}">Group by location</a>
            @else
                <a class="button" href="{{ route('animals.shelter', ['group' => 'enclosure']) }}">Group by enclosure</a>
            @endif
        </div>
    </div>

    @if ($byEnclosure)
        @forelse ($enclosures as $enclosure)
            <section class="card">
                <h2><a href="{{ route('enclosures.show', $enclosure) }}">{{ $enclosure->label() }}</a></h2>
                <p class="hint">Occupants: @include('enclosures._occupancy')</p>
                @if ($enclosure->occupants->isNotEmpty())
                    <ul class="link-list">
                        @foreach ($enclosure->occupants as $animal)
                            <li>
                                <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                                — {{ $animal->species }}
                                @include('animals._badges')
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <p>No enclosures yet. <a href="{{ route('enclosures.index') }}">Add enclosures</a> and place animals in them from the animal record.</p>
        @endforelse

        @if ($unassigned->isNotEmpty())
            <section class="card">
                <h2>No enclosure</h2>
                <ul class="link-list">
                    @foreach ($unassigned as $animal)
                        <li>
                            <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                            — {{ $animal->species }}
                            @include('animals._badges')
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        @forelse ($groups as $location => $animals)
            <section class="card">
                <h2>{{ $location }}</h2>
                <ul class="link-list">
                    @foreach ($animals as $animal)
                        <li>
                            <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                            — {{ $animal->species }}
                            @if ($animal->enclosure)
                                · enclosure <a href="{{ route('enclosures.show', $animal->enclosure) }}">{{ $animal->enclosure->name }}</a>
                            @endif
                            @include('animals._badges')
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p>No animals in care yet.</p>
        @endforelse
    @endif
@endsection
