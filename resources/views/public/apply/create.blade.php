@extends('layouts.public')

@section('title', $type->label().' application · '.$organisationName)

@section('content')
    <p><a href="{{ route('public.apply.index') }}">← All application types</a></p>

    <h1>{{ $type->label() }} application</h1>
    @if ($animal)
        <p class="hint">Applying about <a href="{{ route('public.adopt.show', $animal) }}">{{ $animal->name }}</a> ({{ $animal->species }}).</p>
    @else
        <p class="hint">Tell us about yourself. Fields marked required must be completed.</p>
    @endif

    <form method="post" action="{{ $animal ? route('public.adopt.apply.store', $animal) : route('public.apply.store', $type->value) }}">
        @csrf

        @if ($animal)
            <input type="hidden" name="animal_id" value="{{ $animal->id }}">
        @elseif ($type === \App\Enums\ApplicationType::Adopter)
            <label for="animal_id">Preferred animal (optional)</label>
            <input id="animal_id" name="animal_id" type="number" min="1" value="{{ old('animal_id') }}" placeholder="Animal ID from /adopt">
        @endif

        <fieldset>
            <legend>Contact</legend>
            <label for="name">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required>

            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}">
        </fieldset>

        <fieldset>
            <legend>Address (UK)</legend>
            <label for="address_line1">Address line 1</label>
            <input id="address_line1" name="address_line1" type="text" value="{{ old('address_line1') }}">

            <label for="address_line2">Address line 2</label>
            <input id="address_line2" name="address_line2" type="text" value="{{ old('address_line2') }}">

            <label for="town_city">Town / city</label>
            <input id="town_city" name="town_city" type="text" value="{{ old('town_city') }}">

            <label for="county">County</label>
            <input id="county" name="county" type="text" value="{{ old('county') }}">

            <label for="postcode">Postcode</label>
            <input id="postcode" name="postcode" type="text" value="{{ old('postcode') }}">
        </fieldset>

        <label for="message">Message</label>
        <textarea id="message" name="message">{{ old('message') }}</textarea>

        <button type="submit">Submit application</button>
    </form>
@endsection
