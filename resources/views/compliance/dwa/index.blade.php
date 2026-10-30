@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'DWA licences · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>DWA licences</h1>
            <p class="hint">Dangerous Wild Animals Act licences, the animals they cover and their inspections.</p>
        </div>
        <div class="page-actions">
            @if ($renewalOnly)
                <a class="button" href="{{ route('compliance.dwa.index') }}">All licences</a>
            @else
                <a class="button" href="{{ route('compliance.dwa.index', ['filter' => 'renewal']) }}">Due for renewal</a>
            @endif
            @if (auth()->user()?->role?->canManageAnimals())
                <a class="button button-primary" href="{{ route('compliance.dwa.create') }}">Add licence</a>
            @endif
        </div>
    </div>

    @if ($renewalOnly)
        <p class="filter-chips">
            <span class="chip" data-active-filter="renewal">
                Expired or expiring within {{ \App\Models\DwaLicence::RENEWAL_REMINDER_DAYS }} days
                <a href="{{ route('compliance.dwa.index') }}" aria-label="Remove filter">Remove</a>
            </span>
        </p>
    @endif

    @include('compliance.dwa._not-legal-advice')

    <table>
        <thead>
            <tr><th>Licence</th><th>Council</th><th>Expires</th><th>Animals</th><th>Last inspection</th></tr>
        </thead>
        <tbody>
            @forelse ($licences as $licence)
                @php($lastInspection = $licence->inspections->first())
                <tr data-licence="{{ $licence->id }}">
                    <td><a href="{{ route('compliance.dwa.show', $licence) }}">{{ $licence->licence_number }}</a></td>
                    <td>{{ $licence->issuing_authority }}</td>
                    <td>
                        {{ UkDate::format($licence->expires_on) }}
                        <x-badge :tone="$licence->statusTone()">{{ $licence->statusLabel() }}</x-badge>
                    </td>
                    <td>{{ $licence->animals_count }}</td>
                    <td>{{ $lastInspection ? UkDate::format($lastInspection->inspected_on).' · '.$lastInspection->outcome->label() : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ $renewalOnly ? 'No licences due for renewal.' : 'No DWA licences recorded yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
