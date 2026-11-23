@extends('layouts.app')

@section('title', 'Add observation type · '.config('app.name'))

@section('content')
    <h1>Add observation type</h1>

    <form method="post" action="{{ route('admin.observation-types.store') }}" data-dirty-guard>
        @csrf
        @include('admin.observation-types._form')
        <p>
            <button type="submit">Save</button>
            <a class="button" href="{{ route('admin.observation-types.index') }}">Cancel</a>
        </p>
    </form>
@endsection