@extends('layouts.public')

@section('title', 'Adoptable animals · '.$organisationName)

@section('content')
    <h1>Adoptable animals</h1>
    <p class="hint">Exotic species are shown as entered by the shelter. Dates use UK format (dd/mm/yyyy).</p>

    @if ($animals->isEmpty())
        <p class="hint">No animals are listed for adoption right now.</p>
    @else
        <div class="grid cards">
            @foreach ($animals as $animal)
                <article class="card">
                    @if ($animal->primaryPhotoUrl())
                        <p><img class="thumb" src="{{ $animal->primaryPhotoUrl() }}" alt="Photo of {{ $animal->name }}"></p>
                    @endif
                    <h2><a href="{{ route('public.adopt.show', $animal) }}">{{ $animal->name }}</a></h2>
                    <p>{{ $animal->species }} · {{ $animal->sex->label() }}</p>
                    @if ($animal->colour)
                        <p class="hint">{{ $animal->colour }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        {{ $animals->links() }}
    @endif
@endsection
