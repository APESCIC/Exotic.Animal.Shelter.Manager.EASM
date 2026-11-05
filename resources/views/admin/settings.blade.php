@extends('layouts.app')

@section('title', 'Settings · '.config('app.name'))

@section('content')
    <h1>Organisation settings</h1>
    <p class="hint">Shelter name, UK locale, and timezone. Dates display as dd/mm/yyyy. One shelter per install.</p>
    <p>Today: <strong>{{ \App\Support\UkDate::format(now()) }}</strong></p>

    <form method="post" action="{{ route('admin.settings.update') }}" data-dirty-guard>
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

        <fieldset>
            <legend>Compliance</legend>
            <label for="dwa_reminder_days">DWA licence renewal reminder (days before expiry)</label>
            <input id="dwa_reminder_days" name="dwa_reminder_days" type="number" min="7" max="180" step="1" inputmode="numeric" required
                   value="{{ old('dwa_reminder_days', $settings->dwa_reminder_days ?? \App\Models\Setting::DEFAULT_DWA_REMINDER_DAYS) }}">
            <p class="hint">EASM adds a renewal task to the diary this many days before a Dangerous Wild Animals licence expires. Check your council's renewal window: Herefordshire, for example, takes applications between two months and four weeks before expiry. Not legal advice.</p>
        </fieldset>

        <button type="submit">Save settings</button>
    </form>
@endsection
