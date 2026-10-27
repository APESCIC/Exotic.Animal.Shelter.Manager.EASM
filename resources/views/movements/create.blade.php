@extends('layouts.app')

@section('title', 'Record movement · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Record movement</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}</p>

    <form method="post" action="{{ route('animals.movements.store', $animal) }}" data-dirty-guard>
        @csrf

        <label for="type">Type</label>
        <select id="type" name="type" required>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(old('type', $movement->type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>

        <label for="moved_at">Date</label>
        <input id="moved_at" type="date" name="moved_at" value="{{ old('moved_at', $movement->moved_at instanceof \Carbon\CarbonInterface ? $movement->moved_at->format('Y-m-d') : $movement->moved_at) }}" required>

        <label for="person_id">Contact (optional)</label>
        <select id="person_id" name="person_id">
            <option value="">— None —</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) old('person_id', $movement->person_id) === (string) $person->id)>
                    {{ $person->name }} ({{ $person->categoryLabel() }})
                </option>
            @endforeach
        </select>
        <p class="hint">Use for foster, trial adoption, adoption, reclaim, or transfer contacts.</p>

        <label for="reason">Reason</label>
        <input id="reason" type="text" name="reason" value="{{ old('reason', $movement->reason) }}" maxlength="255">
        <p class="hint">Useful for hold and deceased movements.</p>

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" maxlength="5000">{{ old('notes', $movement->notes) }}</textarea>

        <p>
            <button type="submit">Save movement</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Movements->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
