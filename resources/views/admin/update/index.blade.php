@extends('layouts.app')

@section('title', 'Update · '.config('app.name'))

@section('content')
    <h1>Update EASM</h1>
    <p class="hint">Resumable browser update. Each step is a short request so a 30-second PHP limit is fine. Keep this tab open until it finishes; if it stops, reload and continue.</p>

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
    var uploadStatus = document.querySelector('[data-upload-status]');
    var csrf = root.getAttribute('data-csrf');
    var chunkSize = 4 * 1000 * 1000;

    function show(result) {
        if (message) message.textContent = result.message || result.error || '';
        if (log) log.textContent = JSON.stringify(result.state || result, null, 2);
    }

    function setUploadStatus(text) {
        if (!uploadStatus) return;
        uploadStatus.hidden = !text;
        uploadStatus.textContent = text || '';
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

    var form = document.querySelector('[data-updater-start]');
    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            ensureManualUpload(form).then(function () {
                var data = Object.fromEntries(new FormData(form).entries());
                delete data.package_file;
                delete data.signature_file;
                return postJson(root.getAttribute('data-start-url'), data);
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
})();
</script>
@endpush
