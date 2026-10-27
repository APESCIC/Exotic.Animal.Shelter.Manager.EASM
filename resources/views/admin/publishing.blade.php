@extends('layouts.app')

@section('title', 'Partner feed · '.config('app.name'))

@section('content')
    <h1>Partner feed</h1>
    <p class="hint">Share animals marked <strong>Listed for public adoption</strong> with partner websites and rehoming directories. Partners fetch the feed on their own schedule, so it is always current and needs no cron job. Only public-safe fields are included: no CITES, DWA, location or medical details.</p>

    <section class="card">
        <h2>Download now</h2>
        <p>{{ $adoptableCount }} {{ \Illuminate\Support\Str::plural('animal', $adoptableCount) }} currently listed.</p>
        <p><a class="button button-primary" href="{{ route('admin.publishing.csv') }}" download>Download CSV</a></p>
        <p class="hint">Upload this file to partner sites that accept a spreadsheet.</p>
    </section>

    <section class="card">
        <h2>Live feed</h2>
        <form method="post" action="{{ route('admin.publishing.update') }}">
            @csrf
            @method('PUT')
            <label class="check" for="partner_feed_enabled">
                <input type="hidden" name="partner_feed_enabled" value="0">
                <input id="partner_feed_enabled" name="partner_feed_enabled" type="checkbox" value="1" @checked($enabled)>
                Switch on the live partner feed
            </label>
            <p><button type="submit">Save</button></p>
        </form>

        @if ($enabled && $feedUrls !== [])
            <table>
                <thead>
                    <tr><th>Format</th><th>Feed address (keep private; share only with partners)</th></tr>
                </thead>
                <tbody>
                    @foreach ($feedUrls as $format => $url)
                        <tr>
                            <td>{{ strtoupper($format) }}</td>
                            <td><input type="text" readonly value="{{ $url }}" aria-label="{{ strtoupper($format) }} feed address" onfocus="this.select()"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form method="post" action="{{ route('admin.publishing.regenerate') }}"
                  onsubmit="return window.confirm('Partners using the current addresses will stop receiving updates. Continue?')">
                @csrf
                <button type="submit" class="button-danger">Create new feed address</button>
            </form>
            <p class="hint">Use this if an address has been shared with the wrong people. Old addresses stop working straight away.</p>
        @else
            <p class="hint">The feed is off. Feed addresses return "not found" until you switch it on.</p>
        @endif
    </section>

    <p><a href="{{ route('admin.dashboard') }}">Back to administration</a></p>
@endsection
