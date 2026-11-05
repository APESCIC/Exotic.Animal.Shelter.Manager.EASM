@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Today · Mobile tasks · '.config('app.name'))

@section('content')
    <div class="m-page" data-mobile-page>
        @include('mobile._nav')

        <h1>Today</h1>
        <p class="hint">{{ now()->format('l') }} {{ UkDate::format(now()) }}. Works offline: changes made without signal are saved on this phone and sent when you are back online.</p>

        <section aria-labelledby="m-shifts">
            <h2 id="m-shifts">Your shifts</h2>
            @forelse ($shifts as $shift)
                <p class="m-shift" data-mobile-shift="{{ $shift->id }}"><strong>{{ $shift->timeRange() }}</strong>@if ($shift->area) · {{ $shift->area }}@endif</p>
            @empty
                <p class="hint">No shift for you today. <a href="{{ route('rota.index') }}">See the rota</a>.</p>
            @endforelse
        </section>

        <section aria-labelledby="m-tasks">
            <h2 id="m-tasks">Tasks due ({{ $tasks->count() }})</h2>
            @if ($tasks->isEmpty())
                <p class="hint">Nothing due today.</p>
            @else
                <ul class="m-list">
                    @foreach ($tasks as $task)
                        @include('mobile._task', ['task' => $task])
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="m-medical">
            <h2 id="m-medical">Medication and vaccinations due ({{ $medical->count() }})</h2>
            @if ($medical->isEmpty())
                <p class="hint">Nothing due today.</p>
            @else
                <ul class="m-list">
                    @foreach ($medical as $record)
                        @include('mobile._medical', ['record' => $record])
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="m-actions">
            <a class="m-button" href="{{ route('mobile.scan') }}">Scan an enclosure QR code</a>
            <a class="m-button" href="{{ route('mobile.animals') }}">Find an animal</a>
            <a class="m-button" href="{{ route('rota.mine') }}">My shifts</a>
        </div>
    </div>
@endsection
