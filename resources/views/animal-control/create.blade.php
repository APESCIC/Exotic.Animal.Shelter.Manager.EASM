@extends('layouts.app')

@section('title', 'Add animal control case · '.config('app.name'))

@section('content')
    <h1>Add animal control case</h1>
    <p class="hint">Species stay free-text. After saving you can assign dispatch and close the case from its page.</p>

    <form method="post" action="{{ route('animal-control.store') }}" data-dirty-guard>
        @csrf
        @include('animal-control._form')
        <button type="submit">Create case</button>
        <a class="button" href="{{ route('animal-control.index') }}">Cancel</a>
    </form>
@endsection
