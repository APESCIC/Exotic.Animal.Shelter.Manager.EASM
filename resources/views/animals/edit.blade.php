@extends('layouts.app')

@section('title', 'Edit '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Edit {{ $animal->name }}</h1>

    <form method="post" action="{{ route('animals.update', $animal) }}" enctype="multipart/form-data" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('animals._form')

        <div class="save-bar">
            <span class="save-bar-status" data-dirty-status>No unsaved changes</span>
            <a class="button" href="{{ route('animals.show', $animal) }}">Cancel</a>
            <button type="submit">Save animal</button>
        </div>
    </form>
@endsection
