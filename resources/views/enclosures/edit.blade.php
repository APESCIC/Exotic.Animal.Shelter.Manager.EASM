@extends('layouts.app')

@section('title', 'Edit '.$enclosure->name.' · '.config('app.name'))

@section('content')
    <h1>Edit enclosure</h1>
    <p class="hint">To move animals in or out, edit each animal's record.</p>

    <form method="post" action="{{ route('enclosures.update', $enclosure) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('enclosures._form')
        <p>
            <button type="submit" class="button-primary">Save enclosure</button>
            <a class="button" href="{{ route('enclosures.show', $enclosure) }}">Cancel</a>
        </p>
    </form>
@endsection
