@extends('layouts.app')

@section('title', 'Add diary task · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Add diary task</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}. Assign a staff task with an optional due date.</p>

    <form method="post" action="{{ route('animals.diary.store', $animal) }}">
        @csrf
        @include('diary._form')
        <p>
            <button type="submit">Save diary task</button>
            <a class="button" href="{{ route('animals.show', $animal) }}">Cancel</a>
        </p>
    </form>
@endsection
