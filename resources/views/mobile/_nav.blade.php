<nav class="m-nav" aria-label="Mobile tasks">
    <a href="{{ route('mobile.index') }}"@if (request()->routeIs('mobile.index')) aria-current="page"@endif>Today</a>
    <a href="{{ route('mobile.animals') }}"@if (request()->routeIs('mobile.animals*')) aria-current="page"@endif>Animals</a>
    <a href="{{ route('mobile.scan') }}"@if (request()->routeIs('mobile.scan', 'mobile.enclosures.*')) aria-current="page"@endif>Scan</a>
    <a href="{{ route('rota.mine') }}">My shifts</a>
</nav>
