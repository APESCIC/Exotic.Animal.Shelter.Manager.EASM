@extends('layouts.app')

@section('title', 'Shelter view · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Shelter view</h1>
            <p class="hint">
                Animals in care as a photo grid or list, grouped by {{ strtolower($group->label()) }}.
            </p>
        </div>
    </div>

    <nav class="shelter-toolbar" aria-label="Shelter view options">
        <div class="shelter-toolbar-group" role="group" aria-label="Group by">
            <span class="shelter-toolbar-label">Group by</span>
            @foreach (\App\Enums\ShelterGroup::cases() as $option)
                <a
                    class="button{{ $group === $option ? ' button-primary' : '' }}"
                    href="{{ route('animals.shelter', ['group' => $option->value, 'view' => $viewMode->value]) }}"
                    @if ($group === $option) aria-current="true" @endif
                >{{ $option->label() }}</a>
            @endforeach
        </div>
        <div class="shelter-toolbar-group" role="group" aria-label="Layout">
            <span class="shelter-toolbar-label">Layout</span>
            @foreach (\App\Enums\ShelterViewMode::cases() as $option)
                <a
                    class="button{{ $viewMode === $option ? ' button-primary' : '' }}"
                    href="{{ route('animals.shelter', ['group' => $group->value, 'view' => $option->value]) }}"
                    @if ($viewMode === $option) aria-current="true" @endif
                >{{ $option->label() }}</a>
            @endforeach
        </div>
    </nav>

    @forelse ($sections as $section)
        <section class="card shelter-section">
            <h2>
                @if ($section['url'])
                    <a href="{{ $section['url'] }}">{{ $section['title'] }}</a>
                @else
                    {{ $section['title'] }}
                @endif
            </h2>
            @if ($section['hint'])
                <p class="hint">{{ $section['hint'] }}</p>
            @elseif ($section['enclosure'])
                <p class="hint">Occupants: @include('enclosures._occupancy', ['enclosure' => $section['enclosure']])</p>
            @endif

            @if ($section['animals']->isEmpty())
                <p class="hint">Empty{{ $section['enclosure']?->freeSpaces() !== null ? ' · '.$section['enclosure']->freeSpaces().' free spaces' : '' }}.</p>
            @elseif ($viewMode === \App\Enums\ShelterViewMode::Grid)
                <div class="shelter-grid">
                    @foreach ($section['animals'] as $animal)
                        @include('animals._shelter_tile')
                    @endforeach
                </div>
            @else
                <ul class="link-list shelter-list">
                    @foreach ($section['animals'] as $animal)
                        @include('animals._shelter_list_item')
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <p>No animals in care yet.</p>
    @endforelse
@endsection
