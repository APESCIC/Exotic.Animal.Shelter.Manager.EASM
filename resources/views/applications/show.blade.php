@extends('layouts.app')

@section('title', $application->name.' · Applications · '.config('app.name'))

@section('content')
    <p><a href="{{ route('applications.index') }}">← Applications</a></p>

    <h1>{{ $application->name }}</h1>
    <p class="hint">{{ $application->type->label() }} · {{ $application->status->label() }}</p>

    <table>
        <tr><th>Email</th><td>{{ $application->email ?: '—' }}</td></tr>
        <tr><th>Phone</th><td>{{ $application->phone ?: '—' }}</td></tr>
        <tr><th>Address</th><td>
            @php
                $lines = array_filter([
                    $application->address_line1,
                    $application->address_line2,
                    $application->town_city,
                    $application->county,
                    $application->postcode,
                ]);
            @endphp
            {{ $lines ? implode(', ', $lines) : '—' }}
        </td></tr>
        <tr><th>Preferred animal</th><td>
            @if ($application->animal)
                <a href="{{ route('animals.show', $application->animal) }}">{{ $application->animal->name }}</a>
            @else
                —
            @endif
        </td></tr>
        <tr><th>Message</th><td>{{ $application->message ?: '—' }}</td></tr>
        <tr><th>Submitted</th><td>{{ \App\Support\UkDate::format($application->created_at) }}</td></tr>
        <tr><th>Reviewed</th><td>
            @if ($application->reviewed_at)
                {{ \App\Support\UkDate::format($application->reviewed_at) }}
                @if ($application->reviewer)
                    by {{ $application->reviewer->name }}
                @endif
            @else
                —
            @endif
        </td></tr>
        <tr><th>Status note</th><td>{{ $application->status_note ?: '—' }}</td></tr>
        <tr><th>Linked person</th><td>
            @if ($application->person)
                <a href="{{ route('people.show', $application->person) }}">{{ $application->person->name }}</a>
            @else
                —
            @endif
        </td></tr>
    </table>

    @if (auth()->user()?->role?->canManageApplications() && $application->status->isOpen())
        <h2>Update status</h2>
        <form method="post" action="{{ route('applications.status', $application) }}">
            @csrf
            @method('PATCH')

            <label for="status">Status</label>
            <select id="status" name="status" required>
                @foreach (\App\Enums\ApplicationStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(old('status', $application->status->value) === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>

            <label for="status_note">Status note</label>
            <input id="status_note" name="status_note" type="text" value="{{ old('status_note', $application->status_note) }}">

            <button type="submit">Save status</button>
        </form>

        <form method="post" action="{{ route('applications.accept', $application) }}" style="margin-top:1rem">
            @csrf
            <label for="accept_note">Accept note (optional)</label>
            <input id="accept_note" name="status_note" type="text" value="{{ old('status_note') }}">
            <button type="submit">Accept and create contact</button>
        </form>
    @endif
@endsection
