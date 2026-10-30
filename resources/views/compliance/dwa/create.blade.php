@extends('layouts.app')

@section('title', 'Add DWA licence · '.config('app.name'))

@section('content')
    <h1>Add DWA licence</h1>
    @include('compliance.dwa._not-legal-advice')

    <form method="post" action="{{ route('compliance.dwa.store') }}" data-dirty-guard>
        @csrf
        @include('compliance.dwa._form')
        <p>
            <button type="submit" class="button-primary">Save licence</button>
            <a class="button" href="{{ route('compliance.dwa.index') }}">Cancel</a>
        </p>
    </form>
@endsection
