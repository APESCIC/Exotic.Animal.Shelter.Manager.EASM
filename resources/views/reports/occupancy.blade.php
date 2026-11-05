@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Occupancy report · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Occupancy</h1>
            <p class="hint">Animals in care on {{ UkDate::format($on) }}. <a href="{{ route('reports.index') }}">All reports</a></p>
        </div>
        <div class="page-actions">
            <a class="button button-primary" href="{{ route('reports.occupancy.csv', ['on' => $on->toDateString()]) }}">Download CSV</a>
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('reports.occupancy') }}">
        <div>
            <label for="on">Date</label>
            <input id="on" name="on" type="date" value="{{ $on->toDateString() }}" required>
        </div>
        <div>
            <button type="submit">Show</button>
        </div>
    </form>

    <p class="stat" data-occupancy-total>{{ count($stays) }} in care</p>

    <div class="card-grid">
        <section class="card">
            <h2>By species</h2>
            @if ($bySpecies === [])
                <p class="hint">No animals in care on this date.</p>
            @else
                <table>
                    <thead><tr><th>Species</th><th>Animals</th></tr></thead>
                    <tbody>
                        @foreach ($bySpecies as $species => $count)
                            <tr data-species-row="{{ $species }}"><td>{{ $species }}</td><td>{{ $count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
        <section class="card">
            <h2>By enclosure</h2>
            @if ($byEnclosure === [])
                <p class="hint">No animals in care on this date.</p>
            @else
                <table>
                    <thead><tr><th>Enclosure</th><th>Animals</th></tr></thead>
                    <tbody>
                        @foreach ($byEnclosure as $enclosure => $count)
                            <tr><td>{{ $enclosure }}</td><td>{{ $count }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="hint">Uses each animal's current enclosure.</p>
            @endif
        </section>
    </div>

    @if ($stays !== [])
        <h2>Animals</h2>
        <table>
            <thead><tr><th>Animal</th><th>Species</th><th>Enclosure</th><th>In care since</th><th>Days in care</th></tr></thead>
            <tbody>
                @foreach ($stays as $stay)
                    <tr data-occupancy-animal="{{ $stay->animal->id }}">
                        <td><a href="{{ route('animals.show', $stay->animal) }}">{{ $stay->animal->name }}</a></td>
                        <td>{{ $stay->animal->species }}</td>
                        <td>{{ $stay->animal->enclosure?->name ?? '—' }}</td>
                        <td>{{ UkDate::format($stay->from) }}</td>
                        <td>{{ $stay->daysInCare($on) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
