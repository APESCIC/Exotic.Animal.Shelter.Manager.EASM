@extends('layouts.app')

@section('title', 'Add enclosure · '.config('app.name'))

@section('content')
    <h1>Add enclosure</h1>

    <form method="post" action="{{ route('enclosures.store') }}" data-dirty-guard>
        @csrf
        @include('enclosures._form')
        <p>
            <button type="submit" class="button-primary">Save enclosure</button>
            <a class="button" href="{{ route('enclosures.index') }}">Cancel</a>
        </p>
    </form>
@endsection
