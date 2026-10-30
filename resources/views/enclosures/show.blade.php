@extends('layouts.app')

@section('title', $enclosure->name.' · Enclosures · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $enclosure->name }}</h1>
            <p class="hint">{{ $enclosure->zone ?: 'No zone' }}@if ($enclosure->size) · {{ $enclosure->size }}@endif</p>
        </div>
        @if (auth()->user()?->role?->canManageAnimals())
            <div class="page-actions">
                <a class="button" href="{{ route('enclosures.edit', $enclosure) }}">Edit</a>
            </div>
        @endif
    </div>

    <div class="card-grid">
        <section class="card">
            <h2>Occupants</h2>
            <p class="stat">@include('enclosures._occupancy')</p>
        </section>
    </div>

    @if ($enclosure->notes)
        <p>{{ $enclosure->notes }}</p>
    @endif

    <h2>Animals in care here</h2>
    @if ($enclosure->occupants->isEmpty())
        <p class="hint">No animals in care are placed here.</p>
    @else
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
@endsection
