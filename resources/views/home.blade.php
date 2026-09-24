<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
                margin: 2rem auto;
                max-width: 40rem;
                line-height: 1.5;
                color: #1b1b18;
            }
            a { color: #1b4332; }
            code { font-size: 0.95em; }
            nav { margin: 1rem 0; }
            nav form { display: inline; }
            button.linkish {
                background: none;
                border: none;
                padding: 0;
                font: inherit;
                color: #1b4332;
                text-decoration: underline;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <h1>{{ config('app.name') }}</h1>
        <p>Self-hosted shelter software for exotic species. One shelter per install. Species stay free-text — there is no dog/cat vocabulary in this product.</p>
        <p>Signed in as {{ auth()->user()->name }} ({{ auth()->user()->role->label() }}).</p>
        <p>Version {{ config('app.version') }} · locale {{ config('app.locale') }} · timezone {{ config('app.timezone') }} · today {{ \App\Support\UkDate::format(now()) }}</p>
        <nav>
            <a href="{{ url('/health') }}">Health and version</a>
            · <a href="{{ route('animals.index') }}">Animals</a>
            · <a href="{{ route('animals.shelter') }}">Shelter view</a>
            @if (auth()->user()->role->canManageAnimals())
                · <a href="{{ route('animals.create') }}">Add animal</a>
            @endif
            · <a href="{{ route('people.index') }}">People</a>
            @if (auth()->user()->role->canManagePeople())
                · <a href="{{ route('people.create') }}">Add contact</a>
            @endif
            · <a href="{{ route('lost-found.index') }}">Lost &amp; found</a>
            @if (auth()->user()->role->canManageLostFound())
                · <a href="{{ route('lost-found.create') }}">Add report</a>
            @endif
            · <a href="{{ route('diary.index') }}">Diary / tasks</a>
            @if (auth()->user()->role->isAdmin())
                · <a href="{{ route('admin.dashboard') }}">Administration</a>
                · <a href="{{ route('admin.settings.edit') }}">Settings</a>
            @endif
            ·
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="linkish">Sign out</button>
            </form>
        </nav>
    </body>
</html>
