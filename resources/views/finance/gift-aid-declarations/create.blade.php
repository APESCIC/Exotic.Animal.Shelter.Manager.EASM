@extends('layouts.app')

@section('title', 'Gift Aid declaration · '.$person->name.' · '.config('app.name'))

@section('content')
    <h1>Record Gift Aid declaration</h1>
    <p class="hint">{{ $person->name }}. Keep the signed form or the written confirmation of an oral declaration; HMRC can ask to see it.</p>

    <form method="post" action="{{ route('gift-aid-declarations.store', $person) }}" data-dirty-guard>
        @csrf

        <label for="declared_on">Date of declaration</label>
        <input id="declared_on" type="date" name="declared_on" value="{{ old('declared_on', $declaration->declared_on?->format('Y-m-d')) }}" required>

        <label for="effective_from">Covers donations from</label>
        <input id="effective_from" type="date" name="effective_from" value="{{ old('effective_from', $declaration->effective_from?->format('Y-m-d')) }}" required>
        <p class="hint">A declaration can cover past donations if the donor agrees. Check HMRC's time limits before backdating.</p>

        <label for="method">How it was made</label>
        <select id="method" name="method" required>
            @foreach ($methods as $method)
                <option value="{{ $method->value }}" @selected(old('method', $declaration->method?->value) === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>

        <p>
            <button type="submit" class="button-primary">Save declaration</button>
            <a class="button" href="{{ route('people.show', $person) }}">Cancel</a>
        </p>
    </form>
@endsection
