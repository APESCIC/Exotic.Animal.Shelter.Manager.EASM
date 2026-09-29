<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', $organisationName ?? config('app.name'))</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 48rem;
                line-height: 1.5;
                color: #1b1b18;
            }
            a { color: #1b4332; }
            label { display: block; margin: 0.75rem 0 0.25rem; font-weight: 500; }
            input[type="text"], input[type="email"], input[type="tel"], input[type="number"], select, textarea {
                width: 100%;
                box-sizing: border-box;
                padding: 0.45rem 0.5rem;
                font: inherit;
            }
            textarea { min-height: 5rem; }
            button, .button {
                display: inline-block;
                margin-top: 0.75rem;
                margin-right: 0.5rem;
                padding: 0.55rem 1rem;
                font: inherit;
                cursor: pointer;
                text-decoration: none;
                color: inherit;
                border: 1px solid #888;
                background: transparent;
            }
            .errors {
                background: #fde8e8;
                color: #7f1d1d;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .status {
                background: #e8f5e9;
                color: #1b4332;
                padding: 0.75rem 1rem;
                margin: 1rem 0;
            }
            .hint { margin: 0 0 1rem; color: #444; }
            .photo { max-width: 16rem; height: auto; margin: 0.75rem 0; }
            .thumb { max-width: 6rem; height: auto; }
            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            th, td { text-align: left; padding: 0.4rem 0.35rem; border-bottom: 1px solid #ccc; vertical-align: top; }
            fieldset { border: 1px solid #ccc; margin: 1.25rem 0; padding: 1rem 1.1rem 1.15rem; }
            legend { padding: 0 0.35rem; font-weight: 600; }
            .grid { display: grid; gap: 1.25rem; }
            @media (min-width: 40rem) {
                .grid.cards { grid-template-columns: 1fr 1fr; }
            }
            .card {
                border-bottom: 1px solid #ccc;
                padding-bottom: 1rem;
            }
            header { margin-bottom: 1.5rem; }
            header nav a { margin-right: 0.75rem; }
        </style>
        @stack('head')
    </head>
    <body>
        @unless (!empty($embed))
            <header>
                <p><strong>{{ $organisationName ?? config('app.name') }}</strong></p>
                <nav>
                    <a href="{{ route('public.adopt.index') }}">Adoptable animals</a>
                    <a href="{{ route('public.apply.index') }}">Apply</a>
                </nav>
            </header>
        @endunless

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

        @yield('content')
    </body>
</html>
