@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Rota · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Rota</h1>
            <p class="hint">Week starting {{ $weekStart->format('l') }} {{ UkDate::format($weekStart) }}.</p>
        </div>
        <div class="page-actions">
            <a class="button" href="{{ route('rota.index', ['week' => $weekStart->subWeek()->toDateString()]) }}">Previous week</a>
            <a class="button" href="{{ route('rota.index') }}">This week</a>
            <a class="button" href="{{ route('rota.index', ['week' => $weekStart->addWeek()->toDateString()]) }}">Next week</a>
            <a class="button" href="{{ route('rota.mine') }}">My shifts</a>
            @if ($canManage)
                <a class="button button-primary" href="{{ route('rota.create', ['date' => $weekStart->toDateString()]) }}">Add shift</a>
            @endif
        </div>
    </div>

    <div class="rota-week">
        @foreach ($days as $key => $day)
            <section class="card rota-day{{ $day['date']->isToday() ? ' is-today' : '' }}" data-rota-day="{{ $key }}">
                <h2>{{ $day['date']->format('D') }} {{ UkDate::format($day['date']) }}</h2>
                @forelse ($day['shifts'] as $shift)
                    <div class="rota-shift{{ $shift->user_id === auth()->id() ? ' is-mine' : '' }}" data-rota-shift="{{ $shift->id }}">
                        <strong>{{ $shift->timeRange() }}</strong>
                        <span>{{ $shift->assigneeName() }}</span>
                        @if ($shift->area)
                            <span class="hint">{{ $shift->area }}</span>
                        @endif
                        @if ($canManage)
                            <a href="{{ route('rota.edit', $shift) }}">Edit<span class="visually-hidden"> shift {{ $shift->timeRange() }} {{ $shift->assigneeName() }}</span></a>
                        @endif
                    </div>
                @empty
                    <p class="hint">No shifts.</p>
                @endforelse
                @if ($canManage)
                    <p><a href="{{ route('rota.create', ['date' => $key]) }}">Add<span class="visually-hidden"> shift on {{ UkDate::format($day['date']) }}</span></a></p>
                @endif
            </section>
        @endforeach
    </div>
@endsection
