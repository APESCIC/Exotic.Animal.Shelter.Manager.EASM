@extends('layouts.app')

@section('title', 'Edit observation type · '.config('app.name'))

@section('content')
    <h1>Edit observation type</h1>

    <form method="post" action="{{ route('admin.observation-types.update', $type) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('admin.observation-types._form')
        <p>
            <button type="submit">Save</button>
            <a class="button" href="{{ route('admin.observation-types.index') }}">Cancel</a>
        </p>
    </form>
@endsection