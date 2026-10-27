@extends('layouts.app')

@section('title', 'Edit '.$person->name.' · '.config('app.name'))

@section('content')
    <h1>Edit {{ $person->name }}</h1>

    <form method="post" action="{{ route('people.update', $person) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('people._form')
        <button type="submit">Save contact</button>
        <a class="button" href="{{ route('people.show', $person) }}">Cancel</a>
    </form>
@endsection
