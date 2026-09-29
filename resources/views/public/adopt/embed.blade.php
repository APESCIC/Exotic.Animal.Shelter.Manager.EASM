@extends('layouts.public')

@section('title', 'Adoptable animals · '.$organisationName)

@section('content')
    <h1>{{ $organisationName }} — adoptable animals</h1>

    @if ($animals->isEmpty())
        <p class="hint">No animals are listed for adoption right now.</p>
    @else
        <ul>
            @foreach ($animals as $animal)
                <li>
                    <a href="{{ route('public.adopt.show', $animal) }}" target="_blank" rel="noopener">{{ $animal->name }}</a>
                    — {{ $animal->species }}
                </li>
            @endforeach
        </ul>

        {{ $animals->links() }}
    @endif
@endsection
