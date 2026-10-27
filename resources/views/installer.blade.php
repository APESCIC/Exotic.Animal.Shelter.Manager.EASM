<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Install {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 40rem;
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
            button {
                margin-top: 0.75rem;
                padding: 0.55rem 1rem;
                font: inherit;
                cursor: pointer;
            }
            .errors {
                background: #fde8e8;
                color: #7f1d1d;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .hint { margin: 0 0 1rem; color: #444; }
        </style>
    </head>
    <body>
        <h1>Install Exotic Animal Shelter Manager</h1>
        <p class="hint">One shelter per install. After Composer install, this wizard writes database settings, creates the first admin, and then locks so it cannot be run again.</p>

        @if ($errors->any())
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('install.store') }}">
            @csrf

            @if ($managedDatabase)
            <p class="hint">The hosting platform provides the database, so there are no database settings to enter.</p>
            @else
            <fieldset>
                <legend>Database</legend>
                <p class="hint">Create an empty MySQL or MariaDB database in the host panel, then enter its credentials.</p>
                <label for="db_host">Host</label>
                <input id="db_host" name="db_host" value="{{ old('db_host', $dbDefaults['host']) }}" required autocomplete="off">

                <label for="db_port">Port</label>
                <input id="db_port" name="db_port" type="number" min="1" max="65535" value="{{ old('db_port', $dbDefaults['port']) }}" required>

                <label for="db_database">Database name</label>
                <input id="db_database" name="db_database" value="{{ old('db_database', $dbDefaults['database']) }}" required autocomplete="off">

                <label for="db_username">Username</label>
                <input id="db_username" name="db_username" value="{{ old('db_username', $dbDefaults['username']) }}" required autocomplete="off">

                <label for="db_password">Password</label>
                <input id="db_password" name="db_password" type="password" value="{{ old('db_password') }}" autocomplete="new-password">
            </fieldset>
            @endif

            <fieldset>
                <legend>Organisation</legend>
                <label for="organisation">Organisation name</label>
                <input id="organisation" name="organisation" value="{{ old('organisation') }}" required maxlength="255">

                <label for="timezone">Timezone</label>
                <select id="timezone" name="timezone" required>
                    @foreach ($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', 'Europe/London') === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </fieldset>

            <fieldset>
                <legend>First admin</legend>
                <p class="hint">This user is stored now. Sign-in and roles land in a later step.</p>
                <label for="admin_name">Name</label>
                <input id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required maxlength="255" autocomplete="name">

                <label for="admin_email">Email</label>
                <input id="admin_email" name="admin_email" type="email" value="{{ old('admin_email') }}" required maxlength="255" autocomplete="email">

                <label for="admin_password">Password</label>
                <input id="admin_password" name="admin_password" type="password" required minlength="8" autocomplete="new-password">

                <label for="admin_password_confirmation">Confirm password</label>
                <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="8" autocomplete="new-password">
            </fieldset>

            <button type="submit">Install this shelter</button>
        </form>
    </body>
</html>
