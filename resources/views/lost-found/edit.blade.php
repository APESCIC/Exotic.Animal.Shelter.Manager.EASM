@extends('layouts.app')

@section('title', 'Edit lost/found report · '.config('app.name'))

@section('content')
    <h1>Edit lost/found report</h1>
    <p class="hint">Update details or confirm a matched animal from the suggestions on the report page.</p>

    <form method="post" action="{{ route('lost-found.update', $report) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('lost-found._form')
        <button type="submit">Save changes</button>
        <a class="button" href="{{ route('lost-found.show', $report) }}">Cancel</a>
    </form>
@endsection
