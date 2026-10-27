@extends('layouts.app')

@section('title', 'Add animal · '.config('app.name'))

@section('content')
    <h1>Add animal</h1>
    <p class="hint">Species is free text — there is no dog/cat pick-list.</p>

    <form method="post" action="{{ route('animals.store') }}" enctype="multipart/form-data" data-dirty-guard>
        @csrf
        @include('animals._form')

        <div class="save-bar">
            <span class="save-bar-status" data-dirty-status>No unsaved changes</span>
            <a class="button" href="{{ route('animals.index') }}">Cancel</a>
            <button type="submit">Create animal</button>
        </div>
    </form>
@endsection
