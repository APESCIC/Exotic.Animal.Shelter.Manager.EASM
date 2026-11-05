@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Animal timeline · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Animal timeline</h1>
            <p class="hint">Stays in care between {{ UkDate::format($from) }} and {{ UkDate::format($to) }}. <a href="{{ route('reports.index') }}">All reports</a></p>
        </div>
        <div class="page-actions">
            <a class="button button-primary" href="{{ route('reports.timeline.csv', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download CSV</a>
        </div>
    </div>

    @include('reports._range', ['action' => route('reports.timeline')])

    @if ($rows === [])
        <p class="hint">No animals were in care in this period.</p>
    @else
        <table class="timeline">
            <thead>
                <tr><th>Animal</th><th>Arrived</th><th>Left</th><th>Days</th><th class="timeline-track-head">{{ UkDate::format($from) }} – {{ UkDate::format($to) }}</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($stay = $row['stay'])
                    <tr data-timeline-animal="{{ $stay->animal->id }}">
                        <td>
                            <a href="{{ route('animals.show', $stay->animal) }}">{{ $stay->animal->name }}</a>
                            <span class="hint">{{ $stay->animal->species }}</span>
                        </td>
                        <td>{{ UkDate::format($stay->from) }}</td>
                        <td>{{ $stay->to ? UkDate::format($stay->to).' · '.$stay->outcomeLabel() : 'In care' }}</td>
                        <td>{{ $stay->daysInCare($today) }}</td>
                        <td class="timeline-track" aria-hidden="true">
                            <span class="timeline-bar{{ $stay->to === null ? ' is-open' : '' }}" style="margin-left: {{ $row['offset'] }}%; width: {{ $row['width'] }}%"></span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
