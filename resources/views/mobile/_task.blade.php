@php
    use App\Support\UkDate;
@endphp
<li class="m-item" data-offline-item data-mobile-task="{{ $task->id }}">
    <div>
        <strong>{{ $task->title }}</strong>
        <span class="hint">
            @if ($showAnimal ?? true)
                @if ($task->animal)
                    <a href="{{ route('mobile.animals.show', $task->animal) }}">{{ $task->animal->name }}</a> ·
                @elseif ($task->enclosure)
                    <a href="{{ route('mobile.enclosures.show', $task->enclosure) }}">{{ $task->enclosure->name }}</a> ·
                @endif
            @endif
            @if ($task->due_on)
                {{ $task->due_on->isPast() && ! $task->due_on->isToday() ? 'Overdue, due' : 'Due' }} {{ UkDate::format($task->due_on) }}
            @endif
        </span>
    </div>
    @if (auth()->user()->role->canManageDiary())
        <form method="post" action="{{ route('diary.complete', $task) }}" data-offline-queue data-offline-done="remove"
              data-offline-label="Task done: {{ $task->title }}">
            @csrf
            <button type="submit" class="m-button">Done<span class="visually-hidden">: {{ $task->title }}</span></button>
        </form>
    @endif
</li>
