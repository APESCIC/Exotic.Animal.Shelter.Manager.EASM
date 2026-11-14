<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Install {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('images/pwa/easm.svg') }}" type="image/svg+xml">
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 42rem;
                line-height: 1.5;
                color: #1b1b18;
            }
            h1 { margin-bottom: 0.5rem; }
            fieldset {
                border: 1px solid #ccc;
                margin: 1.25rem 0;
                padding: 1rem 1.1rem 1.15rem;
            }
            legend { padding: 0 0.35rem; font-weight: 600; }
            label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 500; }
            input, select {
                width: 100%;
                box-sizing: border-box;
                padding: 0.45rem 0.5rem;
                font: inherit;
            }
            button, .button {
                margin-top: 0.75rem;
                padding: 0.55rem 1rem;
                font: inherit;
                cursor: pointer;
            }
            .errors, .status-fail {
                background: #fde8e8;
                color: #7f1d1d;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .status-ok {
                background: #e8f5e9;
                color: #1b5e20;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .hint { margin: 0 0 1rem; color: #444; }
            label.choice { display: flex; gap: 0.5rem; align-items: center; font-weight: 400; }
            label.choice input { width: auto; }
            form:has(#db_source_cloudron:checked) .custom-database { display: none; }
            .logo { display: block; width: 10rem; height: auto; margin: 0 0 1rem; }
            .steps { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0 1.5rem; padding: 0; list-style: none; }
            .steps li {
                border: 1px solid #ccc;
                border-radius: 999px;
                padding: 0.25rem 0.75rem;
                font-size: 0.9rem;
            }
            .steps li.active { background: #1b1b18; color: #fff; border-color: #1b1b18; }
            .steps li.done { background: #e8f5e9; border-color: #a5d6a7; }
            .panel[hidden] { display: none !important; }
            .checks { margin: 0; padding-left: 1.1rem; }
            .checks li { margin: 0.35rem 0; }
            .progress { font-variant-numeric: tabular-nums; }
            .actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        </style>
    </head>
    <body>
        <img class="logo" src="{{ asset('images/easm-logo.svg') }}" alt="EASM" width="160" height="183">
        <h1>Install Exotic Animal Shelter Manager</h1>
        <p class="hint">One shelter per install. This wizard checks the host, tests the database, creates the first admin, runs migrations in small steps (safe under a 30-second PHP limit), writes a production <code>.env</code> with an <code>APP_KEY</code>, then locks so it cannot be run again. No shell or Composer on the host.</p>

        <ol class="steps" id="step-list" aria-label="Install steps">
            <li data-step="requirements" class="active">1. Requirements</li>
            <li data-step="database">2. Database</li>
            <li data-step="details">3. Organisation</li>
            <li data-step="migrate">4. Migrate</li>
            <li data-step="finish">5. Finish</li>
        </ol>

        <div id="flash" class="errors" hidden role="alert"></div>

        <section class="panel" data-panel="requirements">
            <h2>Host requirements</h2>
            <p class="hint">Same checks as <code>/check.php</code>, shown here so you can continue in one place.</p>
            <div id="requirements-body"><p class="hint">Checking…</p></div>
            <div class="actions">
                <button type="button" id="btn-requirements-next" disabled>Continue</button>
            </div>
        </section>

        <section class="panel" data-panel="database" hidden>
            <h2>Database</h2>
            @if ($managedDatabase)
            <p class="hint">The hosting platform provides the database, so there are no database settings to enter.</p>
            @else
            <fieldset>
                <legend>Connection</legend>
                @if ($cloudronDatabase)
                <p class="hint">Cloudron provides a MySQL database for this app. Use it, or enter the details of another database.</p>
                <label class="choice"><input type="radio" id="db_source_cloudron" name="db_source" value="cloudron" checked> Use the database Cloudron provides</label>
                <label class="choice"><input type="radio" id="db_source_custom" name="db_source" value="custom"> Enter my own database details</label>
                @else
                <p class="hint">Create an empty MySQL or MariaDB database in the host panel, then enter its credentials.</p>
                @endif

                <div class="custom-database">
                <label for="db_host">Host</label>
                <input id="db_host" name="db_host" value="{{ $dbDefaults['host'] }}" autocomplete="off">

                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" type="number" min="1" max="65535" value="{{ $dbDefaults['port'] }}">

                <label for="db_database">Database name</label>
                <input id="db_database" name="db_database" value="{{ $dbDefaults['database'] }}" autocomplete="off">

                <label for="db_username">Username</label>
                <input id="db_username" name="db_username" value="{{ $dbDefaults['username'] }}" autocomplete="off">

                <label for="db_password">Password</label>
                <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                </div>
            </fieldset>
            @endif
            <div class="actions">
                <button type="button" id="btn-database-next">Test and continue</button>
            </div>
        </section>

        <section class="panel" data-panel="details" hidden>
            <h2>Organisation and first admin</h2>
            <fieldset>
                <legend>Organisation</legend>
                <label for="organisation">Organisation name</label>
                <input id="organisation" name="organisation" required maxlength="255">

                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" required>
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected($timezone === 'Europe/London')>{{ $timezone }}</option>
                    @endforeach
                </select>
            </fieldset>
            <fieldset>
                <legend>First admin</legend>
                <label for="admin_name">Name</label>
                <input id="admin_name" name="admin_name" required maxlength="255" autocomplete="name">

                <label for="admin_email">Email</label>
                <input id="admin_email" name="admin_email" type="email" required maxlength="255" autocomplete="email">

                <label for="admin_password">Password</label>
                <input id="admin_password" name="admin_password" type="password" required minlength="8" autocomplete="new-password">

                <label for="admin_password_confirmation">Confirm password</label>
                <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="8" autocomplete="new-password">
            </fieldset>
            <div class="actions">
                <button type="button" id="btn-details-next">Save and migrate</button>
            </div>
        </section>

        <section class="panel" data-panel="migrate" hidden>
            <h2>Database migrations</h2>
            <p class="hint">Each request runs one migration so a short PHP time limit does not abort the install.</p>
            <p class="progress" id="migrate-progress">Waiting to start…</p>
            <div class="actions">
                <button type="button" id="btn-migrate-run">Run migrations</button>
            </div>
        </section>

        <section class="panel" data-panel="finish" hidden>
            <h2>Finish</h2>
            <p class="hint">Write the production environment file, create the admin account, and lock the installer.</p>
            <div class="actions">
                <button type="button" id="btn-finish">Complete installation</button>
            </div>
        </section>

        <noscript>
            <hr>
            <p class="hint">JavaScript is off, so use the classic one-step form below.</p>
            <form method="post" action="{{ route('install.store') }}">
                @csrf
                @if (! $managedDatabase)
                <fieldset>
                    <legend>Database</legend>
                    <label for="ns_db_host">Host</label>
                    <input id="ns_db_host" name="db_host" value="{{ $dbDefaults['host'] }}">
                    <label for="ns_db_port">Port</label>
                    <input id="ns_db_port" name="db_port" type="number" value="{{ $dbDefaults['port'] }}">
                    <label for="ns_db_database">Database name</label>
                    <input id="ns_db_database" name="db_database" value="{{ $dbDefaults['database'] }}">
                    <label for="ns_db_username">Username</label>
                    <input id="ns_db_username" name="db_username" value="{{ $dbDefaults['username'] }}">
                    <label for="ns_db_password">Password</label>
                    <input id="ns_db_password" name="db_password" type="password">
                </fieldset>
                @endif
                <fieldset>
                    <legend>Organisation</legend>
                    <label for="ns_organisation">Organisation name</label>
                    <input id="ns_organisation" name="organisation" required>
                    <label for="ns_timezone">Timezone</label>
                    <select id="ns_timezone" name="timezone" required>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected($timezone === 'Europe/London')>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </fieldset>
                <fieldset>
                    <legend>First admin</legend>
                    <label for="ns_admin_name">Name</label>
                    <input id="ns_admin_name" name="admin_name" required>
                    <label for="ns_admin_email">Email</label>
                    <input id="ns_admin_email" name="admin_email" type="email" required>
                    <label for="ns_admin_password">Password</label>
                    <input id="ns_admin_password" name="admin_password" type="password" required minlength="8">
                    <label for="ns_admin_password_confirmation">Confirm password</label>
                    <input id="ns_admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="8">
                </fieldset>
                <button type="submit">Install this shelter</button>
            </form>
        </noscript>

        <script>
            (() => {
                const routes = @json($routes);
                const managedDatabase = @json($managedDatabase);
                const cloudronDatabase = @json($cloudronDatabase);
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const flash = document.getElementById('flash');
                const panels = [...document.querySelectorAll('[data-panel]')];
                const stepItems = [...document.querySelectorAll('#step-list [data-step]')];
                let current = 'requirements';

                function showFlash(message, ok) {
                    flash.hidden = !message;
                    flash.textContent = message || '';
                    flash.className = ok ? 'status-ok' : 'errors';
                }

                function showPanel(name) {
                    current = name;
                    panels.forEach((panel) => {
                        panel.hidden = panel.getAttribute('data-panel') !== name;
                    });
                    const order = ['requirements', 'database', 'details', 'migrate', 'finish'];
                    const idx = order.indexOf(name);
                    stepItems.forEach((item) => {
                        const step = item.getAttribute('data-step');
                        const stepIdx = order.indexOf(step);
                        item.classList.toggle('active', step === name);
                        item.classList.toggle('done', stepIdx < idx);
                    });
                }

                async function postJson(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(body || {}),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = data.message
                            || (data.errors && Object.values(data.errors).flat().join(' '))
                            || ('Request failed (' + response.status + ')');
                        throw new Error(message);
                    }
                    return data;
                }

                async function getJson(url) {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || ('Request failed (' + response.status + ')'));
                    }
                    return data;
                }

                function databasePayload() {
                    if (managedDatabase) {
                        return {};
                    }
                    const payload = {
                        db_host: document.getElementById('db_host')?.value || '',
                        db_port: document.getElementById('db_port')?.value || '',
                        db_database: document.getElementById('db_database')?.value || '',
                        db_username: document.getElementById('db_username')?.value || '',
                        db_password: document.getElementById('db_password')?.value || '',
                    };
                    if (cloudronDatabase) {
                        const selected = document.querySelector('input[name="db_source"]:checked');
                        payload.db_source = selected ? selected.value : 'cloudron';
                    }
                    return payload;
                }

                function detailsPayload() {
                    return {
                        organisation: document.getElementById('organisation').value,
                        timezone: document.getElementById('timezone').value,
                        admin_name: document.getElementById('admin_name').value,
                        admin_email: document.getElementById('admin_email').value,
                        admin_password: document.getElementById('admin_password').value,
                        admin_password_confirmation: document.getElementById('admin_password_confirmation').value,
                    };
                }

                async function loadRequirements() {
                    const body = document.getElementById('requirements-body');
                    const next = document.getElementById('btn-requirements-next');
                    try {
                        const report = await getJson(routes.requirements);
                        if (report.locked) {
                            body.innerHTML = '<div class="status-fail">This shelter is already installed. The installer is locked.</div>';
                            next.disabled = true;
                            return;
                        }
                        const items = (report.checks || []).map((check) => {
                            const mark = check.status === 'pass' ? 'OK' : (check.status === 'warn' ? 'Warn' : 'Fail');
                            return '<li><strong>' + mark + '</strong> — ' + (check.label || check.id) + ': ' + (check.detail || '') + '</li>';
                        }).join('');
                        body.innerHTML = '<ul class="checks">' + items + '</ul>';
                        next.disabled = !report.ok;
                        showFlash(report.ok ? 'Host looks ready.' : 'Fix the failed checks before continuing.', report.ok);
                    } catch (error) {
                        body.innerHTML = '<div class="status-fail">' + error.message + '</div>';
                        next.disabled = true;
                    }
                }

                document.getElementById('btn-requirements-next').addEventListener('click', () => {
                    showFlash('', true);
                    showPanel('database');
                });

                document.getElementById('btn-database-next').addEventListener('click', async () => {
                    try {
                        const result = await postJson(routes.database, databasePayload());
                        showFlash(result.message || 'Database OK.', true);
                        showPanel('details');
                    } catch (error) {
                        showFlash(error.message, false);
                    }
                });

                document.getElementById('btn-details-next').addEventListener('click', async () => {
                    try {
                        const result = await postJson(routes.details, detailsPayload());
                        showFlash(result.message || 'Details saved.', true);
                        showPanel('migrate');
                    } catch (error) {
                        showFlash(error.message, false);
                    }
                });

                document.getElementById('btn-migrate-run').addEventListener('click', async () => {
                    const progress = document.getElementById('migrate-progress');
                    const button = document.getElementById('btn-migrate-run');
                    button.disabled = true;
                    try {
                        let done = false;
                        let guard = 0;
                        while (!done && guard < 500) {
                            guard += 1;
                            const result = await postJson(routes.migrate, {});
                            done = !!result.done;
                            progress.textContent = done
                                ? 'Migrations complete.'
                                : ('Ran ' + (result.ran || 0) + '; ' + (result.pending || 0) + ' remaining…');
                        }
                        if (!done) {
                            throw new Error('Migration stopped before finishing. Try Run migrations again.');
                        }
                        showFlash('Migrations complete.', true);
                        showPanel('finish');
                    } catch (error) {
                        showFlash(error.message, false);
                        button.disabled = false;
                    }
                });

                document.getElementById('btn-finish').addEventListener('click', async () => {
                    try {
                        const result = await postJson(routes.finish, {});
                        showFlash(result.message || 'Installed.', true);
                        window.location.href = result.redirect || '/';
                    } catch (error) {
                        showFlash(error.message, false);
                    }
                });

                showPanel('requirements');
                loadRequirements();
            })();
        </script>
    </body>
</html>
