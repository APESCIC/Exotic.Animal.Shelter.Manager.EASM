@extends('layouts.app')

@section('title', 'Edit shift · '.config('app.name'))

@section('content')
    <h1>Edit shift</h1>

    <form method="post" action="{{ route('rota.update', $shift) }}" data-dirty-guard>
        @csrf
        @method('put')
        @include('rota._form')
        <p>
            <button type="submit" class="button-primary">Save shift</button>
            <a class="button" href="{{ route('rota.index', ['week' => $shift->starts_at->toDateString()]) }}">Cancel</a>
        </p>
    </form>

    <form method="post" action="{{ route('rota.destroy', $shift) }}">
        @csrf
        @method('delete')
        <button type="submit" class="button-danger">Remove shift</button>
    </form>
@endsection
