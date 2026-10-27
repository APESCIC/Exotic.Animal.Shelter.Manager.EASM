@extends('layouts.app')

@section('title', 'Dashboard · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="hint">Self-hosted shelter software for exotic species. One shelter per install. Species stay free-text.</p>
        </div>
        @if (auth()->user()->role->canManageAnimals())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('animals.create') }}">Add animal</a>
            </div>
        @endif
    </div>

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
            </ul>
        </section>
        <section class="card">
            <h2>Care</h2>
            <ul class="link-list">
                <li><a href="{{ route('diary.index') }}">Diary and tasks</a></li>
            </ul>
        </section>
    </div>
@endsection
