@extends('layouts.app')

@section('title', 'Add diet · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Add diet</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}</p>

    <form method="post" action="{{ route('animals.diets.store', $animal) }}" data-dirty-guard>
        @csrf
        @include('diets._form')
        <p>
            <button type="submit">Save diet</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Husbandry->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
