@extends('layouts.app')

@section('title', 'Add to waiting list · '.config('app.name'))

@section('content')
    <h1>Add to waiting list</h1>

    <form method="post" action="{{ route('waiting-list.store') }}" data-dirty-guard>
        @csrf
        @include('waiting-list._form')
        <p>
            <button type="submit" class="button-primary">Save</button>
            <a class="button" href="{{ route('waiting-list.index') }}">Cancel</a>
        </p>
    </form>
@endsection
