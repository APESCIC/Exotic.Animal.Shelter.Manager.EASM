@extends('layouts.app')

@section('title', 'Add document template · '.config('app.name'))

@section('content')
    <h1>Add document template</h1>

    <form method="post" action="{{ route('admin.document-templates.store') }}" data-dirty-guard>
        @csrf
        @include('admin.document-templates._form')
        <p>
            <button type="submit" class="button-primary">Save template</button>
            <a class="button" href="{{ route('admin.document-templates.index') }}">Cancel</a>
        </p>
    </form>
@endsection
