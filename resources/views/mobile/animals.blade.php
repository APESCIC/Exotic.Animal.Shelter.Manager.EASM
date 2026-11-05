@extends('layouts.app')

@section('title', 'Animals · Mobile tasks · '.config('app.name'))

@section('content')
    <div class="m-page" data-mobile-page>
        @include('mobile._nav')

        <h1>Animals in care</h1>
        <form method="get" action="{{ route('mobile.animals') }}" role="search" class="m-search">
            <label for="m-q" class="visually-hidden">Name, species or ID</label>
            <input id="m-q" type="search" name="q" value="{{ $q }}" placeholder="Name, species or ID" autocomplete="off">
            <button type="submit" class="m-button">Find</button>
        </form>

        @if ($animals->isEmpty())
            <p class="hint">No animals found.</p>
        @else
            <ul class="m-list">
                @foreach ($animals as $animal)
                    <li class="m-item">
                        <a class="m-link" href="{{ route('mobile.animals.show', $animal) }}" data-mobile-animal="{{ $animal->id }}">
                            <strong>{{ $animal->name }}</strong>
                            <span class="hint">{{ $animal->species }}@if ($animal->enclosure) · {{ $animal->enclosure->name }}@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
