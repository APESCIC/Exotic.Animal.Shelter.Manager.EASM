@extends('layouts.app')

@section('title', 'Diary / tasks · '.config('app.name'))

@section('content')
    <h1>Diary / tasks</h1>
    <p class="hint">Open staff tasks and recently completed items. Animal-linked tasks also appear on the animal record.</p>

    @if ($due)
        <p class="filter-chips">
            <span class="chip" data-active-filter="{{ $due->value }}">
                {{ $due->label() }}
                <a href="{{ route('diary.index') }}" aria-label="Remove filter: {{ $due->label() }}">Remove</a>
            </span>
        </p>
    @endif

    <h2>Open</h2>
    @if ($openTasks->isEmpty())
        <p class="hint">No open tasks.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Animal / enclosure</th>
                    <th>Assignee</th>
                    <th>Due</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openTasks as $task)
                    <tr>
                        <td>{{ $task->title }}</td>
                        <td>
                            @if ($task->animal)
                                <a href="{{ route('animals.show', $task->animal) }}">{{ $task->animal->name }}</a>
                            @elseif ($task->enclosure)
                                <a href="{{ route('enclosures.show', $task->enclosure) }}">{{ $task->enclosure->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                        <td>{{ $task->due_on ? \App\Support\UkDate::format($task->due_on) : '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageDiary())
                                <form class="inline-form" method="post" action="{{ route('diary.complete', $task) }}">
                                    @csrf
                                    <button type="submit">Complete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @unless ($due)
    <h2>Recently completed</h2>
    @if ($recentlyCompleted->isEmpty())
        <p class="hint">No completed tasks yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Animal / enclosure</th>
                    <th>Assignee</th>
                    <th>Completed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentlyCompleted as $task)
                    <tr>
                        <td>{{ $task->title }}</td>
                        <td>
                            @if ($task->animal)
                                <a href="{{ route('animals.show', $task->animal) }}">{{ $task->animal->name }}</a>
                            @elseif ($task->enclosure)
                                <a href="{{ route('enclosures.show', $task->enclosure) }}">{{ $task->enclosure->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                        <td>{{ $task->completed_at ? \App\Support\UkDate::format($task->completed_at) : '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageDiary())
                                <form class="inline-form" method="post" action="{{ route('diary.reopen', $task) }}">
                                    @csrf
                                    <button type="submit">Reopen</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @endunless
@endsection
