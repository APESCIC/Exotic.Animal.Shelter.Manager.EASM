@php
    use App\Support\UkDate;
@endphp
<li class="m-item" data-offline-item data-mobile-medical="{{ $record->id }}">
    <div>
        <strong>{{ $record->type->label() }}: {{ $record->name }}</strong>
        <span class="hint">
            @if ($showAnimal ?? true)
                <a href="{{ route('mobile.animals.show', $record->animal) }}">{{ $record->animal->name }}</a> ·
            @endif
            {{ $record->due_on->isToday() ? 'Due today' : 'Overdue, due '.UkDate::format($record->due_on) }}
        </span>
    </div>
    @if (auth()->user()->role->canManageMedical())
        <form method="post" action="{{ route('medical.given', $record) }}" data-offline-queue data-offline-done="remove"
              data-offline-label="Given: {{ $record->name }} ({{ $record->animal->name }})">
            @csrf
            <input type="hidden" name="given_on" value="{{ today()->toDateString() }}" data-offline-today>
            <button type="submit" class="m-button">Given<span class="visually-hidden">: {{ $record->name }} for {{ $record->animal->name }}</span></button>
        </form>
    @endif
</li>
