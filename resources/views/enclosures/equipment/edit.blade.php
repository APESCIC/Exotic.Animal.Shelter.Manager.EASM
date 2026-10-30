@extends('layouts.app')

@section('title', 'Edit '.$equipment->name.' · '.config('app.name'))

@section('content')
    <h1>Edit equipment</h1>
    <p class="hint">{{ $equipment->enclosure->name }}</p>

    <form method="post" action="{{ route('equipment.update', $equipment) }}" data-dirty-guard>
        @csrf
        @method('PUT')
        @include('enclosures.equipment._form')
        <p>
            <button type="submit" class="button-primary">Save equipment</button>
            <a class="button" href="{{ route('enclosures.show', $equipment->enclosure) }}#equipment">Cancel</a>
        </p>
    </form>
@endsection
