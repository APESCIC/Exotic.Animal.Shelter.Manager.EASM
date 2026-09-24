@extends('layouts.app')

@section('title', 'Edit custom field · '.config('app.name'))

@section('content')
    <h1>Edit custom field</h1>
    <p class="hint">Slug: <code>{{ $definition->slug }}</code> (updates when the label changes).</p>

    <form method="post" action="{{ route('admin.custom-fields.update', $definition) }}">
        @csrf
        @method('PUT')
        @include('admin.custom-fields._form')
        <p>
            <button type="submit">Save field</button>
            <a class="button" href="{{ route('admin.custom-fields.index') }}">Cancel</a>
        </p>
    </form>
@endsection
