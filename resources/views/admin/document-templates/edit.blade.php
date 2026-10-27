@extends('layouts.app')

@section('title', 'Edit document template · '.config('app.name'))

@section('content')
    <h1>Edit document template</h1>
    <p class="hint">Changes apply to documents generated from now on. Existing documents keep the text they were generated with.</p>

    <form method="post" action="{{ route('admin.document-templates.update', $template) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('admin.document-templates._form')
        <p>
            <button type="submit" class="button-primary">Save template</button>
            <a class="button" href="{{ route('admin.document-templates.index') }}">Cancel</a>
        </p>
    </form>
@endsection
