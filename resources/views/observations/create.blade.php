@extends('layouts.app')

@section('title', 'Add observation · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Add observation</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}. Daily journal notes for this animal.</p>

    <form method="post" action="{{ route('animals.observations.store', $animal) }}" data-dirty-guard>
        @csrf

        <label for="observed_on">Date</label>
        <input id="observed_on" type="date" name="observed_on" value="{{ old('observed_on', $observation->observed_on instanceof \Carbon\CarbonInterface ? $observation->observed_on->format('Y-m-d') : $observation->observed_on) }}" required>

        <label for="body">Observation</label>
        <textarea id="body" name="body" required maxlength="5000">{{ old('body', $observation->body) }}</textarea>

        <p>
            <button type="submit">Save observation</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Log->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
