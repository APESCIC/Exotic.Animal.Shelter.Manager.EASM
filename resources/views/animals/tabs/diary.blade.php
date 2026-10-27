@php
    use App\Support\UkDate;
@endphp

<p class="hint">Assigned staff tasks with due dates. Open tasks also appear in the <a href="{{ route('diary.index') }}">diary inbox</a>.</p>
@if ($animal->diaryTasks->isEmpty())
    <p class="hint">No diary tasks yet.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Assignee</th>
                <th>Due</th>
                <th>Status</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($animal->diaryTasks as $task)
                <tr>
                    <td>{{ $task->title }}</td>
                    <td>{{ $task->assignee?->name ?: '—' }}</td>
                    <td>{{ $task->due_on ? UkDate::format($task->due_on) : '—' }}</td>
                    <td>{{ $task->completed_at ? 'Done '.UkDate::format($task->completed_at) : 'Open' }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManageDiary())
                            @if ($task->completed_at)
                                <form class="inline-form" method="post" action="{{ route('diary.reopen', $task) }}">
                                    @csrf
                                    <button type="submit">Reopen</button>
                                </form>
                            @else
                                <form class="inline-form" method="post" action="{{ route('diary.complete', $task) }}">
                                    @csrf
                                    <button type="submit">Complete</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (auth()->user()?->role?->canManageDiary())
    <p><a class="button" href="{{ route('animals.diary.create', $animal) }}">Add diary task</a></p>
@endif
