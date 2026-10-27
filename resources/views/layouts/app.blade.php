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

                    <div class="topbar-search">
                        @yield('topbar-search')
                    </div>

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

                <footer class="app-footer">
                    Version {{ config('app.version') }} · locale {{ config('app.locale') }} · timezone {{ config('app.timezone') }} · today {{ \App\Support\UkDate::format(now()) }}
                    · <a href="{{ url('/health') }}">Health</a>
                </footer>
            </div>
        </div>
    </body>
</html>
