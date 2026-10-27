@extends('layouts.app')

@section('title', 'Administration · '.config('app.name'))

@section('content')
    <h1>Administration</h1>
    <p class="hint">Admin-only area for this shelter install.</p>

    <div class="card-grid">
        <section class="card">
            <h2>Organisation</h2>
            <p class="hint">Shelter name, UK locale, and timezone.</p>
            <a class="button" href="{{ route('admin.settings.edit') }}">Organisation settings</a>
        </section>
        <section class="card">
            <h2>Custom fields</h2>
            <p class="hint">Extra husbandry fields for animal records.</p>
            <a class="button" href="{{ route('admin.custom-fields.index') }}">Custom fields</a>
        </section>
    </div>
@endsection
