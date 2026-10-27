@extends('layouts.app')

@section('title', 'Add stock item · '.config('app.name'))

@section('content')
    <h1>Add stock item</h1>

    <form method="post" action="{{ route('stock.store') }}" data-dirty-guard>
        @csrf
        @include('stock._form')
        <p>
            <button type="submit" class="button-primary">Save item</button>
            <a class="button" href="{{ route('stock.index') }}">Cancel</a>
        </p>
    </form>
@endsection
