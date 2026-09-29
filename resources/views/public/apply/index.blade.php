@extends('layouts.public')

@section('title', 'Apply · '.$organisationName)

@section('content')
    <h1>Apply</h1>
    <p class="hint">Choose an application type. Staff will review submissions in the shelter system.</p>

    <ul>
        @foreach ($types as $type)
            <li><a href="{{ route('public.apply.create', $type->value) }}">{{ $type->label() }} application</a></li>
        @endforeach
    </ul>

    <p><a href="{{ route('public.adopt.index') }}">Browse adoptable animals</a></p>
@endsection
