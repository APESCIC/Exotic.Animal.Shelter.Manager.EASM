@extends('layouts.app')

@section('title', $person->name.' · '.config('app.name'))

@section('content')
    <h1>{{ $person->name }}</h1>
    <p class="hint">{{ $person->categoryLabel() }}
        @if ($person->banned) · Banned @endif
        @if ($person->homechecked) · Homechecked @endif
    </p>

    <table>
        <tr><th>Category</th><td>{{ $person->categoryLabel() }}</td></tr>
        <tr><th>Email</th><td>{{ $person->email ?: '—' }}</td></tr>
        <tr><th>Phone</th><td>{{ $person->phone ?: '—' }}</td></tr>
        <tr><th>Address</th><td>
            @php
                $lines = array_filter([
                    $person->address_line1,
                    $person->address_line2,
                    $person->town_city,
                    $person->county,
                    $person->postcode,
                ]);
            @endphp
            {{ $lines ? implode(', ', $lines) : '—' }}
        </td></tr>
        <tr><th>Banned</th><td>{{ $person->banned ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Homechecked</th><td>{{ $person->homechecked ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Other flags</th><td>{{ $person->flags ?: '—' }}</td></tr>
        <tr><th>Notes</th><td>{{ $person->notes ?: '—' }}</td></tr>
    </table>

    @if (auth()->user()?->role?->canManagePeople())
        <p><a class="button" href="{{ route('people.edit', $person) }}">Edit</a></p>
    @endif

    @if (auth()->user()?->role?->canViewFinance())
        @include('people._finance')
    @endif

    <h2>Documents</h2>
    @include('documents._list', ['documents' => $person->documents])
    @if (auth()->user()?->role?->canManageDocuments())
        <p><a class="button" href="{{ route('documents.create', ['person' => $person->id]) }}">Generate document</a></p>
    @endif
@endsection
