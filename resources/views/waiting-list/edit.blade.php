@extends('layouts.app')

@section('title', 'Edit waiting list entry · '.config('app.name'))

@section('content')
    <h1>Edit waiting list entry</h1>
    <p class="hint">{{ $entry->status->label() }}@if ($entry->closed_on) on {{ \App\Support\UkDate::format($entry->closed_on) }}@endif</p>

    <form method="post" action="{{ route('waiting-list.update', $entry) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('waiting-list._form')
        <p>
            <button type="submit" class="button-primary">Save</button>
            <a class="button" href="{{ route('waiting-list.index') }}">Cancel</a>
        </p>
    </form>
@endsection
