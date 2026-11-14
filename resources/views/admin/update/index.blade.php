@extends('layouts.app')

@section('title', 'Update · '.config('app.name'))

@section('content')
    <h1>Update EASM</h1>
    <p class="hint">Resumable browser update. Each step is a short request so a 30-second PHP limit is fine. Keep this tab open until it finishes; if it stops, reload and continue.</p>

    <section class="card" data-updater
             data-status-url="{{ route('admin.update.status.json') }}"
             data-start-url="{{ route('admin.update.start') }}"
             data-step-url="{{ route('admin.update.step') }}"
             data-csrf="{{ csrf_token() }}">
        <h2>Start update</h2>
        <form data-updater-start>
            <label for="target_version">Target version</label>
            <input id="target_version" name="target_version" type="text" required placeholder="0.9.0" value="{{ old('target_version') }}">

            <label for="package_url">Package HTTPS URL (optional if already uploaded)</label>
            <input id="package_url" name="package_url" type="url" placeholder="https://…/easm-0.9.0.zip">

            <label for="signature_url">Minisign signature URL</label>
            <input id="signature_url" name="signature_url" type="url" placeholder="https://…/easm-0.9.0.zip.minisig">

            <label for="sha256">SHA-256 (64 hex characters)</label>
            <input id="sha256" name="sha256" type="text" minlength="64" maxlength="64" pattern="[a-fA-F0-9]{64}" autocomplete="off">

            <label for="password">Confirm your password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <p><button type="submit" class="button button-primary">Start update</button></p>
        </form>
    </section>

    <section class="card">
        <h2>Progress</h2>
        <p data-updater-message class="hint">{{ ($state['status'] ?? 'idle') === 'idle' ? 'No update in progress.' : 'Status: '.$state['status'] }}</p>
        <pre data-updater-log class="update-notes" aria-live="polite">{{ json_encode($state, JSON_PRETTY_PRINT) }}</pre>
        <p><button type="button" class="button" data-updater-continue>Continue / run next step</button></p>
    </section>

    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.querySelector('[data-updater]');
    if (!root) return;
    var log = document.querySelector('[data-updater-log]');
    var message = document.querySelector('[data-updater-message]');
    var csrf = root.getAttribute('data-csrf');

    function show(result) {
        if (message) message.textContent = result.message || result.error || '';
        if (log) log.textContent = JSON.stringify(result.state || result, null, 2);
    }

    async function post(url, body) {
        var response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body ? JSON.stringify(body) : '{}'
        });
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            show({ message: data.message || ('HTTP ' + response.status), state: data.state || data });
            throw new Error(data.message || ('HTTP ' + response.status));
        }
        show(data);
        return data;
    }

    var form = document.querySelector('[data-updater-start]');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var data = Object.fromEntries(new FormData(form).entries());
            post(root.getAttribute('data-start-url'), data).catch(function () {});
        });
    }

    var cont = document.querySelector('[data-updater-continue]');
    if (cont) {
        cont.addEventListener('click', function () {
            post(root.getAttribute('data-step-url')).catch(function () {});
        });
    }
})();
</script>
@endpush
