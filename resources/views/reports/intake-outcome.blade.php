@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Intake and outcome report · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Intake and outcome</h1>
            <p class="hint">{{ UkDate::format($from) }} to {{ UkDate::format($to) }}. <a href="{{ route('reports.index') }}">All reports</a></p>
        </div>
        <div class="page-actions">
            <a class="button button-primary" href="{{ route('reports.intake-outcome.csv', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download CSV</a>
        </div>
    </div>

    @include('reports._range', ['action' => route('reports.intake-outcome')])

    <table data-intake-outcome-summary>
        <tbody>
            <tr><th scope="row">In care at start ({{ UkDate::format($from) }})</th><td>{{ $summary['in_care_at_start'] }}</td></tr>
            <tr><th scope="row">Intakes</th><td data-intakes>{{ $summary['intakes'] }}</td></tr>
            @foreach ($outcomeTypes as $type)
                <tr><th scope="row">{{ $type->label() }}</th><td data-outcome="{{ $type->value }}">{{ $summary['outcomes'][$type->value] }}</td></tr>
            @endforeach
            <tr><th scope="row">Outcomes total</th><td data-outcomes-total>{{ $summary['outcomes_total'] }}</td></tr>
            <tr><th scope="row">In care at end ({{ UkDate::format($to) }})</th><td>{{ $summary['in_care_at_end'] }}</td></tr>
        </tbody>
    </table>

    <h2>By species</h2>
    @if ($summary['species'] === [])
        <p class="hint">No intakes or outcomes in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Species</th>
                    <th>Intakes</th>
                    @foreach ($outcomeTypes as $type)
                        <th>{{ $type->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['species'] as $species => $row)
                    <tr>
                        <td>{{ $species }}</td>
                        <td>{{ $row['intakes'] }}</td>
                        @foreach ($outcomeTypes as $type)
                            <td>{{ $row['outcomes'][$type->value] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p class="hint">An intake is the start of a stay in care (the first movement, or the record's creation date when it has none). A return after leaving counts as a new intake.</p>
@endsection
