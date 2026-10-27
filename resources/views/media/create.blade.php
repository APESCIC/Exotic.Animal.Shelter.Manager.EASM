@extends('layouts.app')

@section('title', 'Upload media · '.$animal->name.' · '.config('app.name'))

@section('content')
    <h1>Upload media</h1>
    <p class="hint">{{ $animal->name }} · {{ $animal->species }}. Extra photos (max 5&nbsp;MB) or a PDF (max 10&nbsp;MB). Primary photo stays on the animal edit form.</p>

    <form method="post" action="{{ route('animals.media.store', $animal) }}" enctype="multipart/form-data" data-dirty-guard>
        @csrf
        <label for="media_file">File</label>
        <input id="media_file" name="media_file" type="file" accept="image/*,application/pdf" required>
        <p>
            <button type="submit">Upload</button>
            <a class="button" href="{{ \App\Enums\AnimalTab::Media->url($animal) }}">Cancel</a>
        </p>
    </form>
@endsection
