@extends('layouts.app')

@section('title', 'Update · '.config('app.name'))

@section('content')
    <h1>Update EASM</h1>
    <p class="hint">Resumable browser update. Each step is a short request so a 30-second PHP limit is fine. Keep this tab open until it finishes; if it stops, reload and continue. If a step fails, EASM restores the previous version from the pre-update backup and turns maintenance mode off. While maintenance is on, the public sees a 503 page; this progress page stays available after the maintenance bypass cookie is set.</p>

    <section class="card" data-updater
             data-status-url="{{ route('admin.update.status.json') }}"
             data-start-url="{{ route('admin.update.start') }}"
             data-step-url="{{ route('admin.update.step') }}"
             data-upload-begin-url="{{ route('admin.update.upload.begin') }}"
             data-upload-chunk-url="{{ route('admin.update.upload.chunk') }}"
             data-upload-finish-url="{{ route('admin.update.upload.finish') }}"
             data-csrf="{{ csrf_token() }}">
        <h2>Start update</h2>
        <form data-updater-start>
            <label for="target_version">Target version</label>
            <input id="target_version" name="target_version" type="text" required placeholder="0.9.0" value="{{ old('target_version', '0.9.0') }}">

            <h3>Option A — download from GitHub</h3>
            <label for="package_url">Package HTTPS URL</label>
            <input id="package_url" name="package_url" type="url" placeholder="https://…/easm-0.9.0.zip">

            <label for="signature_url">Minisign signature URL</label>
            <input id="signature_url" name="signature_url" type="url" placeholder="https://…/easm-0.9.0.zip.minisig">

            <h3>Option B — manual upload (when outbound HTTPS is blocked)</h3>
            <p class="hint">Upload the Release zip and its <code>.minisig</code> in chunks (each under 4&nbsp;MiB so hosts with an 8&nbsp;M upload limit still work).</p>
            <label for="package_file">Package zip</label>
            <input id="package_file" name="package_file" type="file" accept=".zip,application/zip">
            <label for="signature_file">Minisign signature</label>
            <input id="signature_file" name="signature_file" type="file" accept=".minisig,text/plain">
            <p data-upload-status class="hint" hidden></p>
            <input type="hidden" name="package_path" data-package-path value="">
            <input type="hidden" name="signature_path" data-signature-path value="">

            <label for="sha256">SHA-256 (64 hex characters)</label>
            <input id="sha256" name="sha256" type="text" minlength="64" maxlength="64" pattern="[a-fA-F0-9]{64}" autocomplete="off" required>

            <label for="password">Confirm your password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <p><button type="submit" class="button button-primary">Start update</button></p>
        </form>
    </section>

    <section class="card">
        <h2>Progress</h2>
        <p data-updater-message class="hint">{{ ($state['status'] ?? 'idle') === 'idle' ? 'No update in progress.' : 'Status: '.$state['status'] }}</p>
        @if ($recoveryToken)
            <p class="hint">If Laravel will not boot after a failed update, open
                <a href="{{ url('/recover.php?token='.$recoveryToken) }}"><code>/recover.php</code></a>
                with the recovery token (plain PHP; does not load <code>vendor/</code>).
            </p>
        @endif
        @if ($bypassPath)
            <p class="hint" data-bypass-hint>Maintenance bypass for this admin session:
                <a href="{{ url($bypassPath) }}" data-bypass-link><code>{{ $bypassPath }}</code></a>
                (public visitors still see the 503 maintenance page).
            </p>
        @endif
        <ol class="update-steps" data-updater-steps aria-label="Update steps">
            @foreach (($state['steps_ui'] ?? []) as $step)
                <li data-step-id="{{ $step['id'] }}" data-step-status="{{ $step['status'] }}">
                    <span class="update-step-label">{{ $step['label'] }}</span>
                    <span class="update-step-status">{{ $step['status'] }}</span>
                </li>
            @endforeach
        </ol>
        <pre data-updater-log class="update-notes" aria-live="polite">{{ json_encode($state, JSON_PRETTY_PRINT) }}</pre>
        <p>
            <button type="button" class="button" data-updater-continue>Continue / run next step</button>
            <button type="button" class="button button-primary" data-updater-run-all>Run remaining steps</button>
        </p>
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
    var steps = document.querySelector('[data-updater-steps]');
    var uploadStatus = document.querySelector('[data-upload-status]');
    var csrf = root.getAttribute('data-csrf');
    var chunkSize = 4 * 1000 * 1000;
    var runningAll = false;

    function show(result) {
        if (message) message.textContent = result.message || result.error || '';
        var state = result.state || result;
        if (log) log.textContent = JSON.stringify(state, null, 2);
        renderSteps(state);
        if (result.maintenance_bypass_path) {
            ensureBypass(result.maintenance_bypass_path);
        }
    }

    function renderSteps(state) {
        if (!steps || !state || !state.steps_ui) return;
        state.steps_ui.forEach(function (step) {
            var li = steps.querySelector('[data-step-id="' + step.id + '"]');
            if (!li) return;
            li.setAttribute('data-step-status', step.status);
            var status = li.querySelector('.update-step-status');
            if (status) status.textContent = step.status;
        });
    }

    function setUploadStatus(text) {
        if (!uploadStatus) return;
        uploadStatus.hidden = !text;
        uploadStatus.textContent = text || '';
    }

    async function ensureBypass(path) {
        if (!path) return;
        var link = document.querySelector('[data-bypass-link]');
        var hint = document.querySelector('[data-bypass-hint]');
        if (link) link.setAttribute('href', path);
        if (link) link.textContent = path;
        if (hint) hint.hidden = false;
        try {
            await fetch(path, { credentials: 'same-origin', redirect: 'follow' });
        } catch (e) {
            // Cookie may still be set via redirect; continue.
        }
    }

    async function postJson(url, body) {
        var response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : '{}'
        });
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            show({ message: data.message || data.error || ('HTTP ' + response.status), state: data.state || data, error: data.error || data.message });
            throw new Error(data.message || data.error || ('HTTP ' + response.status));
        }
        show(data);
        return data;
    }

    async function uploadFile(uploadId, kind, file) {
        var index = 0;
        var offset = 0;
        while (offset < file.size) {
            var end = Math.min(offset + chunkSize, file.size);
            var blob = file.slice(offset, end);
            var form = new FormData();
            form.append('upload_id', uploadId);
            form.append('kind', kind);
            form.append('index', String(index));
            form.append('final', end >= file.size ? '1' : '0');
            form.append('chunk', blob, file.name + '.part' + index);
            var response = await fetch(root.getAttribute('data-upload-chunk-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: form
            });
            var data = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                throw new Error(data.message || ('Upload failed (' + response.status + ')'));
            }
            setUploadStatus('Uploaded ' + kind + ': ' + data.bytes + ' bytes');
            offset = end;
            index += 1;
        }
    }

    async function ensureManualUpload(form) {
        var packageInput = form.querySelector('#package_file');
        var signatureInput = form.querySelector('#signature_file');
        var packageFile = packageInput && packageInput.files && packageInput.files[0];
        var signatureFile = signatureInput && signatureInput.files && signatureInput.files[0];
        if (!packageFile && !signatureFile) {
            return;
        }
        if (!packageFile || !signatureFile) {
            throw new Error('Upload both the package zip and the .minisig, or neither.');
        }
        setUploadStatus('Starting upload…');
        var begun = await postJson(root.getAttribute('data-upload-begin-url'), {});
        await uploadFile(begun.upload_id, 'package', packageFile);
        await uploadFile(begun.upload_id, 'signature', signatureFile);
        var finished = await postJson(root.getAttribute('data-upload-finish-url'), { upload_id: begun.upload_id });
        form.querySelector('[data-package-path]').value = finished.package_path;
        form.querySelector('[data-signature-path]').value = finished.signature_path;
        if (finished.sha256 && form.querySelector('#sha256') && !form.querySelector('#sha256').value) {
            form.querySelector('#sha256').value = finished.sha256;
        }
        setUploadStatus('Upload ready.');
    }

    async function runRemaining() {
        if (runningAll) return;
        runningAll = true;
        try {
            var guard = 0;
            while (guard < 80) {
                guard += 1;
                var data = await postJson(root.getAttribute('data-step-url'));
                if (data.done || data.error) break;
            }
        } finally {
            runningAll = false;
        }
    }

    var form = document.querySelector('[data-updater-start]');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            ensureManualUpload(form).then(function () {
                var data = Object.fromEntries(new FormData(form).entries());
                delete data.package_file;
                delete data.signature_file;
                return postJson(root.getAttribute('data-start-url'), data);
            }).then(function () {
                return runRemaining();
            }).catch(function (err) {
                setUploadStatus(err.message || String(err));
            });
        });
    }

    var cont = document.querySelector('[data-updater-continue]');
    if (cont) {
        cont.addEventListener('click', function () {
            postJson(root.getAttribute('data-step-url')).catch(function () {});
        });
    }

    var runAll = document.querySelector('[data-updater-run-all]');
    if (runAll) {
        runAll.addEventListener('click', function () {
            runRemaining().catch(function () {});
        });
    }
})();
</script>
@endpush
