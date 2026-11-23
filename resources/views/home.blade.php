@extends('layouts.app')

@section('title', 'Dashboard · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="hint">What needs attention today, {{ \App\Support\UkDate::format(now()) }}.</p>
        </div>
        @if (auth()->user()->role->canManageAnimals())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('animals.create') }}">Add animal</a>
            </div>
        @endif
    </div>

    <section aria-labelledby="shelter-counts-heading">
        <h2 id="shelter-counts-heading">Shelter</h2>
        <ul class="stat-grid">
            <li>
                <a class="stat-card" href="{{ route('animals.shelter') }}" data-shelter-count="on_shelter">
                    <span class="stat">{{ $shelter['on_shelter'] }}</span>
                    <span class="stat-label">On shelter</span>
                </a>
            </li>
            <li>
                <a class="stat-card" href="{{ route('animals.index', ['filter' => 'quarantine']) }}" data-shelter-count="quarantine">
                    <span class="stat">{{ $shelter['quarantine'] }}</span>
                    <span class="stat-label">In quarantine</span>
                </a>
            </li>
            <li>
                <a class="stat-card" href="{{ route('animals.index') }}" data-shelter-count="available">
                    <span class="stat">{{ $shelter['available'] }}</span>
                    <span class="stat-label">Available for rehoming</span>
                </a>
            </li>
            <li>
                <a class="stat-card" href="{{ route('animals.shelter', ['group' => 'status']) }}" data-shelter-count="fostered">
                    <span class="stat">{{ $shelter['fostered'] }}</span>
                    <span class="stat-label">Fostered</span>
                </a>
            </li>
        </ul>
    </section>

    <section aria-labelledby="alerts-heading">
        <h2 id="alerts-heading" class="visually-hidden">Alerts</h2>
        <ul class="alert-grid">
            @foreach ($alerts as $alert)
                <li>
                    <a class="alert-card alert-card-{{ $alert->tone }} {{ $alert->needsAttention() ? 'is-active' : 'is-clear' }}"
                       href="{{ $alert->url }}"
                       data-alert="{{ $alert->key }}">
                        <span class="alert-count">{{ $alert->count }}</span>
                        <span class="alert-label">{{ $alert->label }}</span>
                        <span class="alert-description">{{ $alert->description }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    <div class="dashboard-columns">
        <section class="card" aria-labelledby="compact-shelter-heading">
            <div class="card-header-row">
                <h2 id="compact-shelter-heading">Shelter view</h2>
                <a href="{{ route('animals.shelter', ['group' => 'enclosure']) }}">Full shelter view</a>
            </div>
            @forelse ($shelter['compact_sections'] as $section)
                <div class="compact-shelter-group">
                    <h3>
                        @if ($section['url'])
                            <a href="{{ $section['url'] }}">{{ $section['title'] }}</a>
                        @else
                            {{ $section['title'] }}
                        @endif
                    </h3>
                    @if ($section['hint'])
                        <p class="hint">{{ $section['hint'] }}</p>
                    @endif
                    @if ($section['animals']->isEmpty())
                        <p class="hint">Empty.</p>
                    @else
                        <div class="shelter-grid shelter-grid-compact">
                            @foreach ($section['animals'] as $animal)
                                @include('animals._shelter_tile')
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="hint">No animals in care yet.</p>
            @endforelse
        </section>

        <section class="card" aria-labelledby="recent-animals-heading">
            <h2 id="recent-animals-heading">Recently changed animals</h2>
            @if ($shelter['recent']->isEmpty())
                <p class="hint">No recent animal updates.</p>
            @else
                <ul class="link-list shelter-list">
                    @foreach ($shelter['recent'] as $animal)
                        <li class="shelter-list-item">
                            @if ($animal->primaryPhotoUrl())
                                <img class="thumb" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}" width="56" height="56" loading="lazy">
                            @else
                                <span class="thumb thumb-empty" role="img" aria-label="No photo for {{ $animal->name }}">No photo</span>
                            @endif
                            <div class="shelter-list-body">
                                <a href="{{ route('animals.show', $animal) }}">{{ $animal->name }}</a>
                                — {{ $animal->species }}
                                <span class="hint">Updated {{ \App\Support\UkDate::format($animal->updated_at) }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="card-grid">
        <section class="card">
            <h2>Animals</h2>
            <ul class="link-list">
                <li><a href="{{ route('animals.index') }}">All animals</a></li>
                <li><a href="{{ route('animals.shelter') }}">Shelter view</a></li>
                <li><a href="{{ route('lost-found.index') }}">Lost and found</a></li>
                <li><a href="{{ route('animal-control.index') }}">Animal control</a></li>
            </ul>
        </section>
        <section class="card">
            <h2>People</h2>
            <ul class="link-list">
                <li><a href="{{ route('people.index') }}">Contacts</a></li>
                <li><a href="{{ route('applications.index') }}">Applications</a></li>
                <li><a href="{{ route('waiting-list.index') }}">Waiting list</a></li>
            </ul>
        </section>
        <section class="card">
            <h2>Care</h2>
            <ul class="link-list">
                <li><a href="{{ route('diary.index') }}">Diary and tasks</a></li>
                <li><a href="{{ route('stock.index') }}">Stock</a></li>
            </ul>
        </section>
    </div>
@endsection
