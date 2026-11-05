@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', $enclosure->name.' · Mobile tasks · '.config('app.name'))

@section('content')
    <div class="m-page" data-mobile-page>
        @include('mobile._nav')

        <h1>{{ $enclosure->name }}</h1>
        <p class="hint">
            {{ $enclosure->zone ?: 'No zone' }} · {{ $enclosure->occupancyLabel() }}
            · <a href="{{ route('enclosures.show', $enclosure) }}">Full record</a>
        </p>

        @if (auth()->user()->role->canRecordHusbandry())
            <section aria-labelledby="m-reading">
                <h2 id="m-reading">Record a reading</h2>
                @if ($lastLog)
                    <p class="hint" data-last-reading>
                        Last: {{ UkDate::format($lastLog->recorded_at) }} {{ $lastLog->recorded_at->format('H:i') }}
                        @if ($lastLog->temperature_hot_c !== null) · hot {{ $lastLog->temperature_hot_c }}°C @endif
                        @if ($lastLog->temperature_cool_c !== null) · cool {{ $lastLog->temperature_cool_c }}°C @endif
                        @if ($lastLog->humidity_percent !== null) · {{ $lastLog->humidity_percent }}% humidity @endif
                    </p>
                @endif
                <form method="post" action="{{ route('enclosures.logs.store', $enclosure) }}" data-offline-queue data-offline-done="reset"
                      data-offline-label="Reading for {{ $enclosure->name }}">
                    @csrf
                    <input type="hidden" name="recorded_at" value="{{ now()->format('Y-m-d\TH:i') }}" data-offline-now>
                    <div class="m-fields">
                        <div>
                            <label for="m-hot">Hot end °C</label>
                            <input id="m-hot" type="number" name="temperature_hot_c" step="0.1" min="-20" max="80" inputmode="decimal">
                        </div>
                        <div>
                            <label for="m-cool">Cool end °C</label>
                            <input id="m-cool" type="number" name="temperature_cool_c" step="0.1" min="-20" max="80" inputmode="decimal">
                        </div>
                        <div>
                            <label for="m-humidity">Humidity %</label>
                            <input id="m-humidity" type="number" name="humidity_percent" step="1" min="0" max="100" inputmode="numeric">
                        </div>
                    </div>
                    <label for="m-notes">Notes</label>
                    <textarea id="m-notes" name="notes" rows="2" maxlength="2000"></textarea>
                    <button type="submit" class="m-button">Save reading</button>
                </form>
            </section>
        @endif

        @if ($enclosure->dueEquipment->isNotEmpty())
            <section aria-labelledby="m-equipment">
                <h2 id="m-equipment">Equipment due</h2>
                <ul class="m-list">
                    @foreach ($enclosure->dueEquipment as $equipment)
                        <li class="m-item">
                            <div>
                                <strong>{{ $equipment->name }}</strong>
                                <span class="hint">{{ $equipment->type->label() }}@if ($equipment->replace_due_on) · replace by {{ UkDate::format($equipment->replace_due_on) }}@endif</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section aria-labelledby="m-occupants">
            <h2 id="m-occupants">Animals ({{ $enclosure->occupants->count() }})</h2>
            @if ($enclosure->occupants->isEmpty())
                <p class="hint">No animals in this enclosure.</p>
            @else
                <ul class="m-list">
                    @foreach ($enclosure->occupants as $animal)
                        <li class="m-item">
                            <a class="m-link" href="{{ route('mobile.animals.show', $animal) }}">
                                <strong>{{ $animal->name }}</strong>
                                <span class="hint">{{ $animal->species }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
