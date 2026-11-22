@extends('layouts.app')

@section('title', 'Add custom field · '.config('app.name'))

@section('content')
    <h1>Add custom field</h1>
    <p class="hint">Choose animal or person. Staff fill values on the matching create/edit form.</p>

    <form method="post" action="{{ route('admin.custom-fields.store') }}" data-dirty-guard>
        @csrf
        @include('admin.custom-fields._form')
        <p>
            <button type="submit">Create field</button>
            <a class="button" href="{{ route('admin.custom-fields.index') }}">Cancel</a>
        </p>
    </form>
@endsection
