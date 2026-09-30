@extends('layouts.public')

@section('title', $animal->name.' · '.$organisationName)

@section('content')
    <p><a href="{{ route('public.adopt.index') }}">← All adoptable animals</a></p>

    <h1>{{ $animal->name }}</h1>
    <p class="hint">{{ $animal->species }}</p>

    @if ($animal->primaryPhotoUrl())
        <img class="photo" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}">
    @endif

    <table>
        <tr><th>Sex</th><td>{{ $animal->sex->label() }}</td></tr>
        <tr><th>Date of birth</th><td>{{ $dateOfBirth ?: '—' }}</td></tr>
        <tr><th>Age (years)</th><td>{{ $animal->age_years ?? '—' }}</td></tr>
        <tr><th>Colour</th><td>{{ $animal->colour ?: '—' }}</td></tr>
        <tr><th>Bonded with</th><td>{{ $animal->bonded_animals ?: '—' }}</td></tr>
    </table>

    @if ($animal->media->isNotEmpty())
        <h2>Photos</h2>
        <div class="grid cards">
            @foreach ($animal->media as $medium)
                <p><img class="thumb" src="{{ $medium->url() }}" alt="{{ $medium->original_name }}"></p>
            @endforeach
        </div>
    @endif

    <p><a class="button" href="{{ route('public.adopt.apply', $animal) }}">Apply to adopt {{ $animal->name }}</a></p>
@endsection
