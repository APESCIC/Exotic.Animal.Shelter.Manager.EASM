@extends('layouts.app')

@section('title', 'Add shift · '.config('app.name'))

@section('content')
    <h1>Add shift</h1>

    <form method="post" action="{{ route('rota.store') }}" data-dirty-guard>
        @csrf
        @include('rota._form')
        <p>
            <button type="submit" class="button-primary">Save shift</button>
            <a class="button" href="{{ route('rota.index', ['week' => $shift->starts_at?->toDateString()]) }}">Cancel</a>
        </p>
    </form>
@endsection
