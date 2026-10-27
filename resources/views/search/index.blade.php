@extends('layouts.app')

@section('title', 'Search · '.config('app.name'))

@section('content')
    <h1>Search</h1>
    <p class="hint">Animals by name, species, microchip or identifying code, CITES or DWA reference. People by name, email, phone or postcode. Press Ctrl+K (⌘K on Mac) anywhere to search.</p>

    <form class="filters search-form" method="get" action="{{ route('search') }}" role="search">
        <div>
            <label for="search-page-q">Find</label>
            <input id="search-page-q" name="q" type="search" value="{{ $q }}" minlength="{{ $minLength }}" autocomplete="off">
        </div>
        <button type="submit">Search</button>
    </form>

    @if (mb_strlen($q) < $minLength)
        <p class="hint">Type at least {{ $minLength }} characters.</p>
    @elseif ($groups === [])
        <p>No animals or people match “{{ $q }}”.</p>
    @else
        @foreach ($groups as $group)
            <section class="card" data-search-group="{{ $group['key'] }}">
                <h2>{{ $group['label'] }}</h2>
                <ul class="result-list">
                    @foreach ($group['items'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                            @if ($item['subtitle'] !== '')
                                <span class="hint">{{ $item['subtitle'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p><a href="{{ $group['more_url'] }}">See all {{ strtolower($group['label']) }} matching “{{ $q }}”</a></p>
            </section>
        @endforeach
    @endif
@endsection
