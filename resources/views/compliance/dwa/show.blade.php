@php
    use App\Enums\AnimalTab;
    use App\Support\UkDate;
    $canManage = auth()->user()?->role?->canManageAnimals();
@endphp
@extends('layouts.app')

@section('title', 'DWA licence '.$licence->licence_number.' · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>DWA licence {{ $licence->licence_number }}
                <x-badge :tone="$licence->statusTone()">{{ $licence->statusLabel() }}</x-badge>
            </h1>
            <p class="hint">{{ $licence->issuing_authority }}</p>
        </div>
        @if ($canManage)
            <div class="page-actions">
                <a class="button" href="{{ route('compliance.dwa.edit', $licence) }}">Edit</a>
            </div>
        @endif
    </div>

    @include('compliance.dwa._not-legal-advice')

    <table>
        <tr><th>Issued</th><td>{{ $licence->issued_on ? UkDate::format($licence->issued_on) : '—' }}</td></tr>
        <tr><th>Expires</th><td>{{ UkDate::format($licence->expires_on) }}</td></tr>
        <tr><th>Species covered</th><td>{{ $licence->species_covered ?: '—' }}</td></tr>
        <tr><th>Conditions</th><td class="prewrap">{{ $licence->conditions ?: '—' }}</td></tr>
        <tr><th>Notes</th><td>{{ $licence->notes ?: '—' }}</td></tr>
    </table>

    <h2>Animals covered</h2>
    @if ($licence->animals->isEmpty())
        <p class="hint">No animals linked to this licence.</p>
    @else
        <ul class="link-list">
            @foreach ($licence->animals as $animal)
                <li data-licence-animal="{{ $animal->id }}">
                    <a href="{{ AnimalTab::Legal->url($animal) }}">{{ $animal->name }}</a>
                    — {{ $animal->species }}
                    @include('animals._badges')
                </li>
            @endforeach
        </ul>
    @endif

    <h2 id="inspections">Inspections</h2>
    @if ($licence->inspections->isEmpty())
        <p class="hint">No inspections recorded.</p>
    @else
        <table>
            <thead>
                <tr><th>Date</th><th>Inspector</th><th>Outcome</th><th>Notes</th><th>Recorded by</th></tr>
            </thead>
            <tbody>
                @foreach ($licence->inspections as $inspection)
                    <tr data-inspection="{{ $inspection->id }}">
                        <td>{{ UkDate::format($inspection->inspected_on) }}</td>
                        <td>{{ $inspection->inspector ?: '—' }}</td>
                        <td><x-badge :tone="$inspection->outcome->badgeTone()">{{ $inspection->outcome->label() }}</x-badge></td>
                        <td>{{ $inspection->notes ?: '—' }}</td>
                        <td>{{ $inspection->recorder?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($canManage)
        <details class="card">
            <summary>Record an inspection</summary>
            <form method="post" action="{{ route('compliance.dwa.inspections.store', $licence) }}">
                @csrf
                <label for="inspected_on">Inspection date</label>
                <input id="inspected_on" type="date" name="inspected_on" value="{{ old('inspected_on', now()->format('Y-m-d')) }}" required>

                <label for="inspector">Inspector</label>
                <input id="inspector" type="text" name="inspector" value="{{ old('inspector') }}" maxlength="255" placeholder="Name and role, e.g. council vet">

                <label for="outcome">Outcome</label>
                <select id="outcome" name="outcome" required>
                    @foreach ($outcomes as $outcome)
                        <option value="{{ $outcome->value }}" @selected(old('outcome') === $outcome->value)>{{ $outcome->label() }}</option>
                    @endforeach
                </select>

                <label for="inspection_notes">Notes</label>
                <textarea id="inspection_notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>

                <p><button type="submit" class="button-primary">Save inspection</button></p>
            </form>
        </details>
    @endif
@endsection
