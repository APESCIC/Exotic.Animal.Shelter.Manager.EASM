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

    <div class="card-grid">
        <section class="card">
            <h2>Animals</h2>
            <ul class="link-list">
                <li><a href="{{ route('animals.index') }}">All animals</a></li>
                <li><a href="{{ route('animals.shelter') }}">Shelter view</a></li>
                <li><a href="{{ route('lost-found.index') }}">Lost and found</a></li>
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
