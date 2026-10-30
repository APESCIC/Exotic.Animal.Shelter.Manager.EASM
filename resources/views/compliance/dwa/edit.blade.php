@extends('layouts.app')

@section('title', 'Edit DWA licence '.$licence->licence_number.' · '.config('app.name'))

@section('content')
    <h1>Edit DWA licence</h1>

    <form method="post" action="{{ route('compliance.dwa.update', $licence) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('compliance.dwa._form')
        <p>
            <button type="submit" class="button-primary">Save licence</button>
            <a class="button" href="{{ route('compliance.dwa.show', $licence) }}">Cancel</a>
        </p>
    </form>
@endsection
