@extends('layouts.app')

@section('title', 'Edit medical record · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Edit medical record</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}. Mark a dose given by setting the given date.</p>

    <form method="post" action="{{ route('medical.update', $record) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('medical._form')
        <p>
            <button type="submit">Save changes</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Health->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
