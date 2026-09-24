@extends('layouts.app')

@section('title', 'Diary / tasks · '.config('app.name'))

@section('content')
    <h1>Diary / tasks</h1>
    <p class="hint">Open staff tasks and recently completed items. Animal-linked tasks also appear on the animal record.</p>

    <h2>Open</h2>
    @if ($openTasks->isEmpty())
        <p class="hint">No open tasks.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Animal</th>
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
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                        <td>{{ $task->due_on ? \App\Support\UkDate::format($task->due_on) : '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageDiary())
                                <form method="post" action="{{ route('diary.complete', $task) }}" style="display:inline">
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

    <h2>Recently completed</h2>
    @if ($recentlyCompleted->isEmpty())
        <p class="hint">No completed tasks yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Animal</th>
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
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                        <td>{{ $task->completed_at ? \App\Support\UkDate::format($task->completed_at) : '—' }}</td>
                        <td>
                            @if (auth()->user()?->role?->canManageDiary())
                                <form method="post" action="{{ route('diary.reopen', $task) }}" style="display:inline">
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
@endsection
