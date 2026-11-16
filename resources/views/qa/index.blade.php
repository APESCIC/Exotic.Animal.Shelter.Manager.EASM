@inject('devLogin', 'App\Support\DevLogin')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Developer QA · {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('images/pwa/easm.svg') }}" type="image/svg+xml">
        <link rel="stylesheet" href="{{ asset('css/easm.css') }}?v={{ @filemtime(public_path('css/easm.css')) }}">
        <style>
            body.qa-page {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 40rem;
                line-height: 1.5;
                color: #1b1b18;
                padding: 0 1rem;
            }
            .qa-page .logo { display: block; width: 10rem; height: auto; margin: 0 0 1rem; }
            .qa-page .hint { margin: 0 0 1rem; color: #444; }
            .qa-page a { color: #1b4332; }
        </style>
    </head>
    <body class="qa-page">
        <img class="logo" src="{{ asset('images/easm-logo.svg') }}" alt="EASM" width="160" height="183">
        <h1>Developer QA</h1>
        <p class="hint">Local Herd only. Prefer the amber panel on the <a href="{{ route('login') }}">sign-in</a> page when you can.</p>

        @if (session('status'))
            <div class="status" role="status">{{ session('status') }}</div>
        @endif

        @include('qa._panel')

        @auth
            <p class="hint">Signed in as {{ auth()->user()->name }}. <a href="{{ route('home') }}">Open the dashboard</a>.</p>
            <form method="post" action="{{ route('qa.logout') }}">
                @csrf
                <button type="submit" class="qa-panel__button">Sign out of QA</button>
            </form>
        @endauth
    </body>
</html>
