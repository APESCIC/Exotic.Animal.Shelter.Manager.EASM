@php
    use App\Models\EnclosureLog;
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', $enclosure->name.' · Enclosures · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $enclosure->name }}</h1>
            <p class="hint">{{ $enclosure->zone ?: 'No zone' }}@if ($enclosure->size) · {{ $enclosure->size }}@endif</p>
        </div>
        @if (auth()->user()?->role?->canManageAnimals())
            <div class="page-actions">
                <a class="button" href="{{ route('enclosures.edit', $enclosure) }}">Edit</a>
            </div>
        @endif
    </div>

    <div class="card-grid">
        <section class="card">
            <h2>Occupants</h2>
            <p class="stat">@include('enclosures._occupancy')</p>
        </section>
    </div>

    @if ($enclosure->notes)
        <p>{{ $enclosure->notes }}</p>
    @endif

    <h2>Animals in care here</h2>
    @if ($enclosure->occupants->isEmpty())
        <p class="hint">No animals in care are placed here.</p>
    @else
        <ul class="link-list">
            @foreach ($enclosure->occupants as $animal)
                <li>
                    <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                    — {{ $animal->species }}
                    @include('animals._badges')
                </li>
            @endforeach
        </ul>
    @endif

    @php
        $role = auth()->user()?->role;
    @endphp

    <h2 id="equipment">Equipment</h2>
    @if ($enclosure->equipment->isEmpty())
        <p class="hint">No equipment recorded. Add UVB lamps, heat sources and other kit to get replacement reminders.</p>
    @else
        <table>
            <thead>
                <tr><th>Equipment</th><th>Type</th><th>Fitted</th><th>Replace by</th><th><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                @foreach ($enclosure->equipment as $item)
                    <tr data-equipment="{{ $item->id }}">
                        <td>
                            {{ $item->name }}
                            @if ($item->isDue()) <x-badge tone="quarantine" data-equipment-due>Due</x-badge>@endif
                            @unless ($item->active) <x-badge>Removed</x-badge>@endunless
                        </td>
                        <td>{{ $item->type->label() }}</td>
                        <td>{{ $item->installed_on ? UkDate::format($item->installed_on) : '—' }}</td>
                        <td>{{ $item->replace_due_on ? UkDate::format($item->replace_due_on) : '—' }}</td>
                        <td>
                            @if ($item->active && $item->replace_every_days && $role?->canRecordHusbandry())
                                <form class="inline-form" method="post" action="{{ route('equipment.replaced', $item) }}">
                                    @csrf
                                    <button type="submit">Mark replaced today</button>
                                </form>
                            @endif
                            @if ($role?->canManageAnimals())
                                <a href="{{ route('equipment.edit', $item) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($role?->canManageAnimals())
        <details class="card">
            <summary>Add equipment</summary>
            <form method="post" action="{{ route('enclosures.equipment.store', $enclosure) }}">
                @csrf
                @include('enclosures.equipment._form', ['equipment' => new \App\Models\EnclosureEquipment(['active' => true])])
                <p><button type="submit" class="button-primary">Add equipment</button></p>
            </form>
        </details>
    @endif

    <h2 id="environment">Environment log</h2>
    @if ($role?->canRecordHusbandry())
        <section class="card">
            <h3>Record a reading</h3>
            <form method="post" action="{{ route('enclosures.logs.store', $enclosure) }}">
                @csrf
                <label for="recorded_at">When</label>
                <input id="recorded_at" type="datetime-local" name="recorded_at" value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}" required>

                <label for="temperature_hot_c">Hot end (°C)</label>
                <input id="temperature_hot_c" type="number" step="0.1" name="temperature_hot_c" value="{{ old('temperature_hot_c') }}">

                <label for="temperature_cool_c">Cool end (°C)</label>
                <input id="temperature_cool_c" type="number" step="0.1" name="temperature_cool_c" value="{{ old('temperature_cool_c') }}">

                <label for="humidity_percent">Humidity (%)</label>
                <input id="humidity_percent" type="number" min="0" max="100" name="humidity_percent" value="{{ old('humidity_percent') }}">

                <label for="log_notes">Notes</label>
                <input id="log_notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="2000">

                <p><button type="submit" class="button-primary">Save reading</button></p>
            </form>
        </section>
    @endif

    @if ($logs->isEmpty())
        <p class="hint">No readings recorded yet.</p>
    @else
        <table>
            <thead>
                <tr><th>When</th><th>Hot end</th><th>Cool end</th><th>Humidity</th><th>Notes</th><th>By</th></tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr data-log="{{ $log->id }}">
                        <td>{{ UkDate::format($log->recorded_at) }} {{ $log->recorded_at->format('H:i') }}</td>
                        <td>{{ EnclosureLog::formatTemperature($log->temperature_hot_c) }}</td>
                        <td>{{ EnclosureLog::formatTemperature($log->temperature_cool_c) }}</td>
                        <td>{{ $log->humidity_percent !== null ? $log->humidity_percent.'%' : '—' }}</td>
                        <td>{{ $log->notes ?: '—' }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $logs->links() }}
    @endif
@endsection
