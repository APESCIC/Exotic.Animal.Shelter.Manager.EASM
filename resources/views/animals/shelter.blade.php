@extends('layouts.app')

@section('title', 'Shelter view · '.config('app.name'))

@section('content')
    <h1>Shelter view</h1>
    <p class="hint">Animals grouped by location / unit.</p>

    @forelse ($groups as $location => $animals)
        <section class="card">
            <h2>{{ $location }}</h2>
            <ul class="link-list">
                @foreach ($animals as $animal)
                    <li>
                        <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                        — {{ $animal->species }}
                        @if ($animal->enclosure)
                            · enclosure {{ $animal->enclosure }}
                        @endif
                        @include('animals._badges')
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>No animals in care yet.</p>
    @endforelse
@endsection
