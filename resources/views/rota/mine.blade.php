@php
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'My shifts · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>My shifts</h1>
            <p class="hint">Your upcoming shifts. <a href="{{ route('rota.index') }}">Full rota</a></p>
        </div>
    </div>

    @if ($shifts->isEmpty())
        <p class="hint">You have no upcoming shifts.</p>
    @else
        <ul class="result-list">
            @foreach ($shifts as $shift)
                <li data-my-shift="{{ $shift->id }}">
                    <strong>{{ $shift->starts_at->format('D') }} {{ UkDate::format($shift->starts_at) }}, {{ $shift->timeRange() }}</strong>
                    @if ($shift->area)
                        · {{ $shift->area }}
                    @endif
                    @if ($shift->notes)
                        <span class="hint">{{ $shift->notes }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
