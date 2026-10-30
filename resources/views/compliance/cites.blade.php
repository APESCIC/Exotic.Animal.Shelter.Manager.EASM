@php
    use App\Enums\AnimalTab;
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'CITES register · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>CITES register</h1>
            <p class="hint">
                Animals with a CITES Annex, reference or certificate on file,
                {{ $includeDeparted ? 'including animals no longer in care' : 'currently in care' }}.
            </p>
        </div>
        <div class="page-actions">
            @if ($includeDeparted)
                <a class="button" href="{{ route('compliance.cites.index') }}">In care only</a>
            @else
                <a class="button" href="{{ route('compliance.cites.index', ['all' => 1]) }}">Include departed</a>
            @endif
            <a class="button button-primary" href="{{ route('compliance.cites.csv', $includeDeparted ? ['all' => 1] : []) }}">Download CSV</a>
        </div>
    </div>

    @include('compliance._not-legal-advice')

    <table>
        <thead>
            <tr>
                <th>Animal</th>
                <th>Species</th>
                <th>Annex</th>
                <th>Reference</th>
                <th>Certificates</th>
                <th>Article 10</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($animals as $animal)
                @php($articleTen = $register->articleTenStatus($animal))
                <tr data-register-animal="{{ $animal->id }}">
                    <td><a href="{{ AnimalTab::Legal->url($animal) }}">{{ $animal->name }}</a></td>
                    <td>{{ $animal->species }}</td>
                    <td>{{ $animal->cites_annex?->label() ?? 'Not recorded' }}</td>
                    <td>{{ $animal->cites ?: '—' }}</td>
                    <td>
                        @forelse ($animal->citesCertificates as $certificate)
                            {{ $certificate->summary() }}@if ($certificate->issued_on) ({{ UkDate::format($certificate->issued_on) }})@endif
                            @unless ($loop->last)<br>@endunless
                        @empty
                            —
                        @endforelse
                    </td>
                    <td>
                        @if ($articleTen === 'Missing')
                            <x-badge tone="dwa" data-missing-article-10>Missing</x-badge>
                        @else
                            {{ $articleTen }}
                        @endif
                    </td>
                    <td>{{ $register->status($animal) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No animals on the register. Set a CITES Annex on an animal record to add it.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
