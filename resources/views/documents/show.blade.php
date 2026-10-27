@extends('layouts.app')

@section('title', $document->title.' · '.config('app.name'))

@push('head')
    <script src="{{ asset('js/signature-pad.js') }}?v={{ @filemtime(public_path('js/signature-pad.js')) }}" defer></script>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $document->title }}</h1>
            <p class="hint">
                {{ $document->type->label() }}
                · created {{ \App\Support\UkDate::format($document->created_at) }}@if ($document->creator) by {{ $document->creator->name }}@endif
                @if ($document->animal) · <a href="{{ \App\Enums\AnimalTab::Documents->url($document->animal) }}">{{ $document->animal->name }}</a>@endif
                @if ($document->person) · <a href="{{ route('people.show', $document->person) }}">{{ $document->person->name }}</a>@endif
            </p>
        </div>
        <div class="page-actions">
            <a class="button" href="{{ route('documents.pdf', $document) }}">Download PDF</a>
        </div>
    </div>

    <article class="card document-body">
        {!! $document->body_html !!}
    </article>

    @if ($document->isSigned())
        <section class="card">
            <h2>Signature</h2>
            <p>Signed by <strong>{{ $document->signed_by_name }}</strong> on {{ \App\Support\UkDate::format($document->signed_at) }}.</p>
            <p class="hint">This document is archived and cannot be changed.</p>
        </section>
    @elseif (auth()->user()?->role?->canManageDocuments())
        <section class="card">
            <h2>Sign</h2>
            <form method="post" action="{{ route('documents.sign', $document) }}" data-signature-form>
                @csrf
                <label for="signed_by_name">Signer's full name</label>
                <input id="signed_by_name" type="text" name="signed_by_name" value="{{ old('signed_by_name', $document->person?->name) }}" required maxlength="255">

                <p class="field-label" id="signature-label">Signature</p>
                <canvas class="signature-pad" width="600" height="200" data-signature-canvas
                        role="img" aria-labelledby="signature-label" aria-describedby="signature-help"></canvas>
                <p class="hint" id="signature-help">Sign with a mouse, finger or stylus.</p>
                <input type="hidden" name="signature" data-signature-input>

                <p>
                    <button type="submit" class="button-primary">Sign and archive</button>
                    <button type="button" class="button" data-signature-clear>Clear</button>
                </p>
            </form>
        </section>
    @endif
@endsection
