@extends('layouts.app')

@section('title', 'Add value · '.$list->name.' · '.config('app.name'))

@section('content')
    <h1>Add value to {{ $list->name }}</h1>

    <form method="post" action="{{ route('admin.lookups.values.store', $list) }}" data-dirty-guard>
        @csrf
        @include('admin.lookups._form')
        <p>
            <button type="submit">Add value</button>
            <a class="button" href="{{ route('admin.lookups.show', $list) }}">Cancel</a>
        </p>
    </form>
@endsection
