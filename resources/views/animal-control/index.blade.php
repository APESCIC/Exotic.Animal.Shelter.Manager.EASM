@extends('layouts.app')

@section('title', 'Animal control · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Animal control</h1>
            <p class="hint">Log municipal-style callouts, assign dispatch, and close cases when the work is done.</p>
        </div>
        @if (auth()->user()?->role?->canManageAnimalControl())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('animal-control.create') }}">Add case</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('animal-control.index') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="text" value="{{ $q }}">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $caseStatus)
                    <option value="{{ $caseStatus->value }}" @selected($status === $caseStatus->value)>{{ $caseStatus->label() }}</option>
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
                <th>Reference</th>
                <th>Status</th>
                <th>Reported</th>
                <th>Location</th>
                <th>Summary</th>
                <th>Assigned</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cases as $case)
                <tr>
                    <td><a href="{{ route('animal-control.show', $case) }}">{{ $case->reference }}</a></td>
                    <td>{{ $case->status->label() }}</td>
                    <td>{{ \App\Support\UkDate::format($case->reported_at) }}</td>
                    <td>{{ $case->location_area }}</td>
                    <td>{{ $case->summary }}</td>
                    <td>{{ $case->assignedUser?->name ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No animal control cases match.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $cases->links() }}
@endsection
