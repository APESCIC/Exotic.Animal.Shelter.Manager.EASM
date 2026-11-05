@php
    $role = auth()->user()->role;
@endphp
@extends('layouts.app')

@section('title', $animal->name.' · Mobile tasks · '.config('app.name'))

@section('content')
    <div class="m-page" data-mobile-page>
        @include('mobile._nav')

        <h1>{{ $animal->name }}</h1>
        <p class="hint">
            {{ $animal->species }}
            @if ($animal->enclosure)
                · <a href="{{ route('mobile.enclosures.show', $animal->enclosure) }}">{{ $animal->enclosure->name }}</a>
            @endif
            · <a href="{{ route('animals.show', $animal) }}">Full record</a>
        </p>
        @include('animals._badges', ['animal' => $animal])

        @if ($role->canManageMedical())
            <section aria-labelledby="m-log">
                <h2 id="m-log">Add to the log</h2>
                <form method="post" action="{{ route('animals.observations.store', $animal) }}" data-offline-queue data-offline-done="reset"
                      data-offline-label="Log entry for {{ $animal->name }}">
                    @csrf
                    <input type="hidden" name="observed_on" value="{{ today()->toDateString() }}" data-offline-today>
                    <label for="m-body">What did you see or do?</label>
                    <textarea id="m-body" name="body" rows="3" required maxlength="5000" placeholder="Ate two locusts, shed complete"></textarea>
                    <button type="submit" class="m-button">Save log entry</button>
                </form>
            </section>
        @endif

        @if ($role->canManageMedia())
            <section aria-labelledby="m-photo">
                <h2 id="m-photo">Photo</h2>
                <form method="post" action="{{ route('animals.media.store', $animal) }}" enctype="multipart/form-data" data-offline-queue data-offline-done="reset"
                      data-offline-label="Photo of {{ $animal->name }}">
                    @csrf
                    <label for="m-photo-file">Take or choose a photo</label>
                    <input id="m-photo-file" type="file" name="media_file" accept="image/*" capture="environment" required>
                    <button type="submit" class="m-button">Upload photo</button>
                </form>
            </section>
        @endif

        <section aria-labelledby="m-animal-tasks">
            <h2 id="m-animal-tasks">Open tasks ({{ $tasks->count() }})</h2>
            @if ($tasks->isEmpty())
                <p class="hint">No open tasks.</p>
            @else
                <ul class="m-list">
                    @foreach ($tasks as $task)
                        @include('mobile._task', ['task' => $task, 'showAnimal' => false])
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="m-animal-medical">
            <h2 id="m-animal-medical">Medication and vaccinations due ({{ $medical->count() }})</h2>
            @if ($medical->isEmpty())
                <p class="hint">Nothing due.</p>
            @else
                <ul class="m-list">
                    @foreach ($medical as $record)
                        @include('mobile._medical', ['record' => $record->setRelation('animal', $animal), 'showAnimal' => false])
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($unsigned->isNotEmpty())
            <section aria-labelledby="m-sign">
                <h2 id="m-sign">Waiting for a signature</h2>
                <ul class="m-list">
                    @foreach ($unsigned as $document)
                        <li class="m-item">
                            <a class="m-link" href="{{ route('documents.show', $document) }}">
                                <strong>{{ $document->title }}</strong>
                                <span class="hint">Sign on this phone with a finger</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
