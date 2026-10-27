@extends('layouts.app')

@section('title', 'Add lost/found report · '.config('app.name'))

@section('content')
    <h1>Add lost/found report</h1>
    <p class="hint">Species stay free-text. After saving, the report page lists likely matches against animals in care or recently adopted.</p>

    <form method="post" action="{{ route('lost-found.store') }}" data-dirty-guard>
        @csrf
        @include('lost-found._form')
        <button type="submit">Create report</button>
        <a class="button" href="{{ route('lost-found.index') }}">Cancel</a>
    </form>
@endsection
