@inject('devLogin', 'App\Support\DevLogin')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sign in · {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('images/pwa/easm.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="{{ asset('css/easm.css') }}?v={{ @filemtime(public_path('css/easm.css')) }}">
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 40rem;
                line-height: 1.5;
                color: #1b1b18;
                background: #fff;
                padding: 0 1rem;
            }
            h1 { margin-bottom: 0.5rem; }
            label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 500; }
            input[type="email"], input[type="password"] {
                width: 100%;
                box-sizing: border-box;
                padding: 0.45rem 0.5rem;
                font: inherit;
                color: #1b1b18;
                background: #fff;
                border: 1px solid #888;
            }
            .remember {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 0.85rem;
                font-weight: 400;
            }
            .remember input { width: auto; }
            button[type="submit"]:not(.qa-panel__button) {
                margin-top: 0.75rem;
                padding: 0.55rem 1rem;
                font: inherit;
                cursor: pointer;
                color: #1b1b18;
                background: #f0f0ee;
                border: 1px solid #888;
            }
            .errors {
                background: #fde8e8;
                color: #7f1d1d;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .hint { margin: 0 0 1rem; color: #444; }
            .logo { display: block; width: 10rem; height: auto; margin: 0 0 1rem; }
            @media (prefers-color-scheme: dark) {
                body { color: #ededec; background: #121212; }
                input[type="email"], input[type="password"] {
                    color: #ededec;
                    background: #1e1e1e;
                    border-color: #777;
                }
                button[type="submit"]:not(.qa-panel__button) {
                    color: #ededec;
                    background: #2a2a2a;
                    border-color: #777;
                }
                .errors { background: #3b1515; color: #fecaca; }
                .hint { color: #b0b0a8; }
            }
        </style>
    </head>
    <body>
        <img class="logo" src="{{ asset('images/easm-logo.svg') }}" alt="EASM" width="160" height="183">
        <h1>Sign in</h1>
        <p class="hint">Email and password for this shelter. No external SSO.</p>

        @if (session('status'))
            <div class="status" role="status">{{ session('status') }}</div>
        @endif

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
            @include('qa._panel')
        @endif
    </body>
</html>
