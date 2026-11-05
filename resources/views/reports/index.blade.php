@extends('layouts.app')

@section('title', 'Reports · '.config('app.name'))

@section('content')
    <h1>Reports</h1>
    <p class="hint">Built from each animal's movement history. Every report can be downloaded as CSV.</p>

    <div class="card-grid">
        <section class="card">
            <h2><a href="{{ route('reports.occupancy') }}">Occupancy</a></h2>
            <p class="hint">Animals in care on a chosen date, by species and enclosure.</p>
        </section>
        <section class="card">
            <h2><a href="{{ route('reports.intake-outcome') }}">Intake and outcome</a></h2>
            <p class="hint">Arrivals and departures (adoption, reclaim, transfer, deceased) over a date range.</p>
        </section>
        <section class="card">
            <h2><a href="{{ route('reports.timeline') }}">Animal timeline</a></h2>
            <p class="hint">Each stay in care over a date range, with arrival, departure and days in care.</p>
        </section>
    </div>
@endsection
