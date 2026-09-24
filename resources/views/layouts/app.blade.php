<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>
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
            input[type="text"], input[type="number"], input[type="date"], input[type="email"], input[type="password"], input[type="file"], select, textarea {
                width: 100%;
                box-sizing: border-box;
                padding: 0.45rem 0.5rem;
                font: inherit;
            }
            textarea { min-height: 4rem; }
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
            .check { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.85rem; font-weight: 400; }
            .check input { width: auto; }
            table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
            th, td { text-align: left; padding: 0.4rem 0.35rem; border-bottom: 1px solid #ccc; vertical-align: top; }
            .photo { max-width: 12rem; height: auto; margin: 0.75rem 0; }
            .thumb { max-width: 3.5rem; height: auto; }
            nav { margin: 1rem 0; }
            fieldset { border: 1px solid #ccc; margin: 1.25rem 0; padding: 1rem 1.1rem 1.15rem; }
            legend { padding: 0 0.35rem; font-weight: 600; }
            .filters { display: grid; gap: 0.75rem; margin: 1rem 0; }
            @media (min-width: 40rem) {
                .filters { grid-template-columns: 1fr 1fr auto; align-items: end; }
                .filters button { margin-top: 0; }
            }
        </style>
    </head>
    <body>
        <p><a href="{{ route('home') }}">{{ config('app.name') }}</a>
            · <a href="{{ route('animals.index') }}">Animals</a>
            · <a href="{{ route('animals.shelter') }}">Shelter view</a>
            @if (auth()->user()?->role?->canManageAnimals())
                · <a href="{{ route('animals.create') }}">Add animal</a>
            @endif
            · <a href="{{ route('people.index') }}">People</a>
            @if (auth()->user()?->role?->canManagePeople())
                · <a href="{{ route('people.create') }}">Add contact</a>
            @endif
            · <a href="{{ route('lost-found.index') }}">Lost &amp; found</a>
            @if (auth()->user()?->role?->canManageLostFound())
                · <a href="{{ route('lost-found.create') }}">Add report</a>
            @endif
            · <a href="{{ route('diary.index') }}">Diary / tasks</a>
        </p>

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
