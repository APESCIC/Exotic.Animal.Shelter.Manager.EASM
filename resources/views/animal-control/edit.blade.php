@extends('layouts.app')

@section('title', 'Edit '.$case->reference.' · '.config('app.name'))

@section('content')
    <h1>Edit {{ $case->reference }}</h1>
    <p class="hint">Status stays {{ $case->status->label() }} here; use the case page to dispatch or close.</p>

    <form method="post" action="{{ route('animal-control.update', $case) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('animal-control._form')
        <button type="submit">Save changes</button>
        <a class="button" href="{{ route('animal-control.show', $case) }}">Cancel</a>
    </form>
@endsection
