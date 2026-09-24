@extends('layouts.app')

@section('title', 'Add custom field · '.config('app.name'))

@section('content')
    <h1>Add custom field</h1>
    <p class="hint">Animal entity only for now. Staff fill values on the animal create/edit form.</p>

    <form method="post" action="{{ route('admin.custom-fields.store') }}">
        @csrf
        @include('admin.custom-fields._form')
        <p>
            <button type="submit">Create field</button>
            <a class="button" href="{{ route('admin.custom-fields.index') }}">Cancel</a>
        </p>
    </form>
@endsection
