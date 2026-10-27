@extends('layouts.app')

@section('title', 'Add sponsorship · '.config('app.name'))

@section('content')
    <h1>Add sponsorship</h1>
    <p class="hint">Records who sponsors which animal. Record each payment received in the ledger as a Sponsorship payment.</p>

    <form method="post" action="{{ route('sponsorships.store') }}" data-dirty-guard>
        @csrf

        <label for="animal_id">Animal</label>
        <select id="animal_id" name="animal_id" required>
            <option value="">— Choose —</option>
            @foreach ($animals as $animal)
                <option value="{{ $animal->id }}" @selected((string) old('animal_id', $sponsorship->animal_id) === (string) $animal->id)>{{ $animal->name }} ({{ $animal->species }})</option>
            @endforeach
        </select>

        <label for="person_id">Sponsor</label>
        <select id="person_id" name="person_id" required>
            <option value="">— Choose —</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) old('person_id', $sponsorship->person_id) === (string) $person->id)>{{ $person->name }}</option>
            @endforeach
        </select>

        <label for="amount">Amount (£)</label>
        <input id="amount" type="text" name="amount" inputmode="decimal" value="{{ old('amount') }}" required maxlength="10" placeholder="5.00">

        <label for="frequency">Frequency</label>
        <select id="frequency" name="frequency" required>
            @foreach ($frequencies as $frequency)
                <option value="{{ $frequency->value }}" @selected(old('frequency', $sponsorship->frequency?->value) === $frequency->value)>{{ $frequency->label() }}</option>
            @endforeach
        </select>

        <label for="starts_on">Starts</label>
        <input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $sponsorship->starts_on?->format('Y-m-d')) }}" required>

        <label for="ends_on">Ends (optional)</label>
        <input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on') }}">

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" maxlength="5000">{{ old('notes') }}</textarea>

        <p>
            <button type="submit" class="button-primary">Save sponsorship</button>
            <a class="button" href="{{ route('finance.index') }}">Cancel</a>
        </p>
    </form>
@endsection
