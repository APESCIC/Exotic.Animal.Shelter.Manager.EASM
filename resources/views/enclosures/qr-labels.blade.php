@extends('layouts.app')

@section('title', 'Enclosure QR labels · '.config('app.name'))

@section('content')
    <div class="page-header no-print">
        <div>
            <h1>Enclosure QR labels</h1>
            <p class="hint">Stick a label on each enclosure. Scanning it with a phone opens the enclosure's care page, where staff can record readings and see the animals inside.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="button button-primary" data-print>Print</button>
        </div>
    </div>

    @if ($labels->isEmpty())
        <p class="hint">No active enclosures yet.</p>
    @else
        <div class="qr-labels">
            @foreach ($labels as $label)
                <figure class="qr-label" data-qr-label="{{ $label['enclosure']->id }}">
                    {!! $label['svg'] !!}
                    <figcaption>
                        <strong>{{ $label['enclosure']->name }}</strong>
                        @if ($label['enclosure']->zone)
                            <span>{{ $label['enclosure']->zone }}</span>
                        @endif
                        <small>{{ $label['url'] }}</small>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif
@endsection
