@extends('layouts.app')

@section('title', 'Add contact · '.config('app.name'))

@section('content')
    <h1>Add contact</h1>
    <p class="hint">Choose a category (or Custom) and set banned / homechecked flags as needed.</p>

    <form method="post" action="{{ route('people.store') }}" data-dirty-guard>
        @csrf
        @include('people._form')
        <button type="submit">Create contact</button>
        <a class="button" href="{{ route('people.index') }}">Cancel</a>
    </form>
@endsection
