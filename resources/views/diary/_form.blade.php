@php
    $dateValue = function ($value): string {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    };
@endphp

<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old('title', $task->title) }}" required maxlength="255">

<label for="assigned_to">Assign to</label>
<select id="assigned_to" name="assigned_to" required>
    @foreach ($assignees as $user)
        <option value="{{ $user->id }}" @selected((string) old('assigned_to', $task->assigned_to) === (string) $user->id)>
            {{ $user->name }} ({{ $user->role->label() }})
        </option>
    @endforeach
</select>

<label for="due_on">Due</label>
<input id="due_on" type="date" name="due_on" value="{{ old('due_on', $dateValue($task->due_on)) }}">

<label for="body">Details</label>
<textarea id="body" name="body" maxlength="5000">{{ old('body', $task->body) }}</textarea>
