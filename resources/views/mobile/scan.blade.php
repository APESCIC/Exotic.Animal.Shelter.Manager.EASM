@extends('layouts.app')

@section('title', 'Scan · Mobile tasks · '.config('app.name'))

@push('head')
    <script src="{{ asset('js/qr-scan.js') }}?v={{ @filemtime(public_path('js/qr-scan.js')) }}" defer></script>
@endpush

@section('content')
    <div class="m-page" data-mobile-page>
        @include('mobile._nav')

        <h1>Scan an enclosure</h1>
        <div class="m-scan" data-qr-scan data-allowed-prefix="{{ url('/m/') }}">
            <video class="m-scan-video" data-qr-video playsinline muted hidden></video>
            <button type="button" class="m-button" data-qr-start>Start camera</button>
            <p class="hint" role="status" aria-live="polite" data-qr-status>
                Point the camera at the QR label on the enclosure. If this phone cannot scan here, open the phone's own camera app: it reads the label and opens the page.
            </p>
        </div>

        <form method="get" class="m-search" data-enclosure-jump>
            <label for="m-enclosure">Or choose the enclosure</label>
            <select id="m-enclosure" name="enclosure" data-enclosure-select>
                <option value="">Choose…</option>
                @foreach ($enclosures as $enclosure)
                    <option value="{{ route('mobile.enclosures.show', $enclosure) }}">{{ $enclosure->name }}@if ($enclosure->zone) ({{ $enclosure->zone }})@endif</option>
                @endforeach
            </select>
        </form>
    </div>
@endsection
