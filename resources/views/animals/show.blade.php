@extends('layouts.app')

@section('title', $animal->name.' · '.($activeTab === \App\Enums\AnimalTab::Overview ? '' : $activeTab->label().' · ').config('app.name'))

@section('content')
    <div class="page-header animal-header">
        <div class="animal-identity">
            @if ($animal->primaryPhotoUrl() && $activeTab !== \App\Enums\AnimalTab::Overview)
                <img class="animal-avatar" src="{{ $animal->primaryPhotoUrl() }}" alt="Primary photo of {{ $animal->name }}">
            @endif
            <div>
                <h1>{{ $animal->name }} @include('animals._badges')</h1>
                <p class="hint">{{ $animal->species }}@if ($animal->breed_type) · {{ $animal->breed_type }}@endif
                    @if ($animal->identifying_code) · {{ $animal->identifying_code }}@endif
                    @if ($animal->isPubliclyAdoptable()) · Available for adoption @endif
                </p>
            </div>
        </div>
        @if (auth()->user()?->role?->canManageAnimals())
            <div class="page-actions">
                <a class="button button-primary" href="{{ route('animals.edit', $animal) }}">Edit</a>
            </div>
        @endif
    </div>

    <nav class="tabs" aria-label="Animal record sections">
        <ul>
            @foreach ($tabs as $tab)
                @php
                    $counted = $tab->countedRelation();
                    $count = $counted ? $animal->{\Illuminate\Support\Str::snake($counted).'_count'} : null;
                @endphp
                <li>
                    <a class="tab" href="{{ $tab->url($animal) }}" data-tab="{{ $tab->value }}"@if ($tab === $activeTab) aria-current="page"@endif>
                        {{ $tab->label() }}@if ($count)<span class="tab-count" aria-label="{{ $count }} records">{{ $count }}</span>@endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <section class="tab-panel" aria-labelledby="tab-heading" data-active-tab="{{ $activeTab->value }}">
        <h2 id="tab-heading" class="visually-hidden">{{ $activeTab->label() }}</h2>
        @include('animals.tabs.'.$activeTab->value)
    </section>
@endsection
