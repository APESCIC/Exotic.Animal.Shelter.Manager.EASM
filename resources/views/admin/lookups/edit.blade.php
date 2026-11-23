@extends('layouts.app')

@section('title', 'Edit value · '.$list->name.' · '.config('app.name'))

@section('content')
    <h1>Edit value in {{ $list->name }}</h1>

    <form method="post" action="{{ route('admin.lookups.values.update', [$list, $value]) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('admin.lookups._form')
        <p>
            <button type="submit">Save changes</button>
            <a class="button" href="{{ route('admin.lookups.show', $list) }}">Cancel</a>
        </p>
    </form>
@endsection
