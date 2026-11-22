@extends('layouts.app')

@section('title', $case->reference.' · '.config('app.name'))

@section('content')
    <h1>{{ $case->reference }}</h1>
    <p class="hint">{{ $case->status->label() }}
        · Reported {{ \App\Support\UkDate::format($case->reported_at) }}
        @if ($case->location_area) · {{ $case->location_area }} @endif
    </p>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <table>
        <tr><th>Reference</th><td>{{ $case->reference }}</td></tr>
        <tr><th>Status</th><td>{{ $case->status->label() }}</td></tr>
        <tr><th>Reported</th><td>{{ $case->reported_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Location / area</th><td>{{ $case->location_area }}</td></tr>
        <tr><th>Species</th><td>{{ $case->species ?: '—' }}</td></tr>
        <tr><th>Summary</th><td>{{ $case->summary }}</td></tr>
        <tr><th>Notes</th><td>{{ $case->notes ?: '—' }}</td></tr>
        <tr><th>Reporter</th><td>
            @if ($case->reporter)
                <a href="{{ route('people.show', $case->reporter) }}">{{ $case->reporter->name }}</a>
            @else
                —
            @endif
        </td></tr>
        <tr><th>Linked animal</th><td>
            @if ($case->linkedAnimal)
                <a href="{{ route('animals.show', $case->linkedAnimal) }}">{{ $case->linkedAnimal->name }}</a>
                ({{ $case->linkedAnimal->species }})
            @else
                —
            @endif
        </td></tr>
        <tr><th>Assigned to</th><td>{{ $case->assignedUser?->name ?: '—' }}</td></tr>
        <tr><th>Dispatched</th><td>{{ $case->dispatched_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</td></tr>
        <tr><th>Closed</th><td>{{ $case->closed_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</td></tr>
        <tr><th>Close notes</th><td>{{ $case->close_notes ?: '—' }}</td></tr>
    </table>

    @if (auth()->user()?->role?->canManageAnimalControl())
        @if ($case->status !== \App\Enums\AnimalControlCaseStatus::Closed)
            <h2>Assign dispatch</h2>
            <form method="post" action="{{ route('animal-control.dispatch', $case) }}">
                @csrf
                <label for="assigned_user_id">Dispatcher</label>
                <select id="assigned_user_id" name="assigned_user_id" required>
                    <option value="">Choose staff</option>
                    @foreach ($dispatchers as $dispatcher)
                        <option value="{{ $dispatcher->id }}" @selected((string) old('assigned_user_id', $case->assigned_user_id) === (string) $dispatcher->id)>{{ $dispatcher->name }}</option>
                    @endforeach
                </select>
                <button type="submit">Assign dispatch</button>
            </form>

            <h2>Close case</h2>
            <form method="post" action="{{ route('animal-control.close', $case) }}">
                @csrf
                <label for="close_notes">Close notes</label>
                <textarea id="close_notes" name="close_notes">{{ old('close_notes') }}</textarea>
                <button type="submit">Close case</button>
            </form>
        @endif

        <p>
            @if ($case->status !== \App\Enums\AnimalControlCaseStatus::Closed)
                <a class="button" href="{{ route('animal-control.edit', $case) }}">Edit</a>
            @endif
            <a class="button" href="{{ route('animal-control.index') }}">All cases</a>
        </p>
    @else
        <p><a class="button" href="{{ route('animal-control.index') }}">All cases</a></p>
    @endif
@endsection
