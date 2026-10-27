@extends('layouts.app')

@section('title', 'Lost and found · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Lost and found</h1>
            <p class="hint">File lost or found reports for exotic animals and review likely matches against animals in care or recently adopted.</p>
        </div>
        @if (auth()->user()?->role?->canManageLostFound())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('lost-found.create') }}">Add report</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('lost-found.index') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit">Search</button>
        </div>
    </form>

    <table>
        <thead>
            <tr>
                <th>Type</th>
                <th>Species</th>
                <th>Reported</th>
                <th>Area</th>
                <th>Matched animal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td><a href="{{ route('lost-found.show', $report) }}">{{ $report->type->label() }}</a></td>
                    <td>{{ $report->species }}</td>
                    <td>{{ \App\Support\UkDate::format($report->reported_at) }}</td>
                    <td>{{ $report->location_area ?: '—' }}</td>
                    <td>
                        @if ($report->matchedAnimal)
                            <a href="{{ route('animals.show', $report->matchedAnimal) }}">{{ $report->matchedAnimal->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No lost/found reports match.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $reports->links() }}
@endsection
