@extends('layouts.app')

@section('title', 'Add medical record · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Add medical record</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}. Names stay free-text for exotic schedules.</p>

    <form method="post" action="{{ route('animals.medical.store', $animal) }}" data-dirty-guard>
        @csrf
        @include('medical._form')
        <p>
            <button type="submit">Save medical record</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Health->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
