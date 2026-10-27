@extends('layouts.app')

@section('title', 'Settings · '.config('app.name'))

@section('content')
    <h1>Organisation settings</h1>
    <p class="hint">Shelter name, UK locale, and timezone. Dates display as dd/mm/yyyy. One shelter per install.</p>
    <p>Today: <strong>{{ \App\Support\UkDate::format(now()) }}</strong></p>

    <form method="post" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>Organisation</legend>
            <label for="organisation_name">Organisation name</label>
            <input id="organisation_name" name="organisation_name" value="{{ old('organisation_name', $settings->organisation_name) }}" required>

            <label for="locale">Locale</label>
            <select id="locale" name="locale" required>
                <option value="en_GB" @selected(old('locale', $settings->locale) === 'en_GB')>United Kingdom (en_GB)</option>
                <option value="en_US" @selected(old('locale', $settings->locale) === 'en_US')>United States (en_US)</option>
            </select>

            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone" required>
                @foreach ($timezones as $timezone)
                    <option value="{{ $timezone }}" @selected(old('timezone', $settings->timezone) === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
        </fieldset>

        <button type="submit">Save settings</button>
    </form>
@endsection
