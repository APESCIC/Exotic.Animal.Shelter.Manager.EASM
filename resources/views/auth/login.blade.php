@inject('devLogin', 'App\Support\DevLogin')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sign in · {{ config('app.name') }}</title>
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
            label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 500; }
            input[type="email"], input[type="password"] {
                width: 100%;
                box-sizing: border-box;
                padding: 0.45rem 0.5rem;
                font: inherit;
            }
            .remember {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 0.85rem;
                font-weight: 400;
            }
            .remember input { width: auto; }
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
            .dev-login {
                margin-top: 2rem;
                padding: 0.75rem 1rem;
                border: 1px dashed #b45309;
                background: #fffbeb;
            }
            .dev-login h2 { margin: 0 0 0.5rem; font-size: 1.05rem; }
        </style>
    </head>
    <body>
        <h1>Sign in</h1>
        <p class="hint">Email and password for this shelter. No Cloudron or external SSO in v0.1.</p>

        @if ($errors->any())
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <label class="remember" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                Remember me
            </label>

            <button type="submit">Sign in</button>
        </form>

        @if ($devLogin->enabled())
            <section class="dev-login" aria-labelledby="dev-login-title" data-dev-login>
                <h2 id="dev-login-title">Development: sign in as</h2>
                <p class="hint">Local QA accounts (password <code>{{ \App\Support\DevLogin::PASSWORD }}</code>). Auto-login stays paused after signing out until you pick a role.</p>
                <ul>
                    @foreach (\App\Enums\UserRole::cases() as $devRole)
                        <li><a href="{{ route('dev.login-as', $devRole) }}">{{ $devRole->label() }}</a> · {{ \App\Support\DevLogin::email($devRole) }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </body>
</html>
