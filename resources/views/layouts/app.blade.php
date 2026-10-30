<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>
        <script>
            (function () {
                var theme = null;
                try { theme = localStorage.getItem('easm-theme'); } catch (e) {}
                if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) { theme = 'dark'; }
                document.documentElement.setAttribute('data-theme', theme || 'light');
            })();
        </script>
        <link rel="stylesheet" href="{{ asset('css/easm.css') }}?v={{ @filemtime(public_path('css/easm.css')) }}">
        <script src="{{ asset('js/easm.js') }}?v={{ @filemtime(public_path('js/easm.js')) }}" defer></script>
        @stack('head')
    </head>
    <body data-layout="app">
        <a class="skip-link" href="#main">Skip to content</a>

        <div class="shell">
            @include('layouts._sidebar')
            <div class="sidebar-backdrop" data-nav-close></div>

            <div class="main-col">
                <header class="topbar">
                    <button type="button" class="icon-button nav-toggle" data-nav-toggle aria-controls="sidebar" aria-expanded="false">Menu</button>

                    <form class="topbar-search" method="get" action="{{ route('search') }}" role="search">
                        <label class="visually-hidden" for="topbar-q">Search animals and people</label>
                        <input id="topbar-q" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                               placeholder="Search animals, people, microchips…" autocomplete="off" data-search-input>
                        <button type="button" class="icon-button palette-hint" data-palette-open aria-haspopup="dialog" aria-controls="command-palette" title="Open quick search">
                            <kbd>Ctrl</kbd><kbd>K</kbd>
                        </button>
                    </form>

                    <div class="topbar-actions">
                        <button type="button" class="icon-button" data-theme-toggle aria-pressed="false">Dark mode</button>
                        @auth
                            <span class="topbar-user"><strong>{{ auth()->user()->name }}</strong> <span>({{ auth()->user()->role->label() }})</span></span>
                            <form class="inline-form" method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="linkish">Sign out</button>
                            </form>
                        @endauth
                    </div>
                </header>

                <main id="main" class="content" tabindex="-1">
                    @if (session('status'))
                        <div class="status" role="status">{{ session('status') }}</div>
                    @endif

                    @if (session('warning'))
                        <div class="warning" role="alert">{{ session('warning') }}</div>
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
                </main>

                <dialog class="palette" id="command-palette" aria-label="Quick search" data-palette
                        data-search-url="{{ route('search.json') }}">
                    <form class="palette-box" method="get" action="{{ route('search') }}" role="search" data-palette-form>
                        <label class="visually-hidden" for="palette-input">Search animals and people</label>
                        <input id="palette-input" name="q" type="search" autocomplete="off" spellcheck="false"
                               placeholder="Animal, person, microchip or CITES reference"
                               role="combobox" aria-expanded="false" aria-autocomplete="list"
                               aria-controls="palette-results" aria-activedescendant="" data-palette-input>
                        <div id="palette-results" class="palette-results" role="listbox" aria-label="Search results" data-palette-results></div>
                        <p class="palette-status" role="status" aria-live="polite" data-palette-status>Type at least 2 characters.</p>
                        <p class="palette-help"><kbd>↑</kbd><kbd>↓</kbd> move · <kbd>Enter</kbd> open · <kbd>Esc</kbd> close</p>
                    </form>
                </dialog>

                <footer class="app-footer">
                    Version {{ config('app.version') }} · locale {{ config('app.locale') }} · timezone {{ config('app.timezone') }} · today {{ \App\Support\UkDate::format(now()) }}
                    · <a href="{{ url('/health') }}">Health</a>
                </footer>
            </div>
        </div>
    </body>
</html>
