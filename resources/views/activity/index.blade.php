@extends('layouts.app')

@section('title', 'Recent changes · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Recent changes</h1>
            <p class="hint">
                Audit log for admin and staff. This is not the care-stay report at
                <a href="{{ route('reports.timeline') }}">Reports → Timeline</a>.
            </p>
        </div>
    </div>

    <form class="filters" method="get" action="{{ route('activity') }}">
        <div>
            <label for="q">Find</label>
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Action, user or record type">
        </div>
        <div>
            <label for="type">Record type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="user_id">User</label>
            <select id="user_id" name="user_id">
                <option value="">Anyone</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($userId === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Filter</button>
    </form>

    @forelse ($grouped as $date => $dayEntries)
        <section class="card activity-day">
            <h2>
                @if ($date === $today)
                    Today
                @elseif ($date === $yesterday)
                    Yesterday
                @else
                    {{ \App\Support\UkDate::format($date) }}
                @endif
            </h2>
            <ul class="activity-list">
                @foreach ($dayEntries as $entry)
                    <li class="activity-item">
                        <time datetime="{{ $entry->created_at?->toIso8601String() }}">
                            {{ $entry->created_at?->timezone(config('app.timezone'))->format('H:i') }}
                        </time>
                        <div>
                            @if ($entry->recordUrl())
                                <a href="{{ $entry->recordUrl() }}">{{ $entry->summary() }}</a>
                            @else
                                {{ $entry->summary() }}
                            @endif
                            <span class="hint">
                                {{ $entry->subjectLabel() }}
                                · {{ $entry->user?->name ?: 'System' }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p>No matching changes.</p>
    @endforelse

    {{ $entries->links() }}
@endsection
