@php
    use App\Support\Money;
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Gift Aid · '.config('app.name'))

@section('content')
    <h1>Gift Aid schedule <x-badge tone="quarantine">Draft</x-badge></h1>
    <div class="errors" role="note">
        <p><strong>Draft only. Do not submit this to HMRC without checking it.</strong> The columns follow the HMRC Charities Online schedule spreadsheet as we understand it, but they have not been checked against the current HMRC specification or by a legal or tax adviser. Check the rules and the format on GOV.UK before you make a claim.</p>
    </div>

    <form class="filters" method="get" action="{{ route('gift-aid.index') }}">
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="{{ $from }}" required>
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="{{ $to }}" required>
        </div>
        <div>
            <button type="submit">Show</button>
        </div>
    </form>
    <p class="hint">Defaults to the current UK tax year (from 6 April). Covers payments marked Gift Aid eligible.</p>

    <h2>Ready to claim ({{ count($claimable) }})</h2>
    @if ($claimable === [])
        <p class="hint">No claimable donations in this period.</p>
    @else
        <table>
            <thead>
                <tr><th>First name</th><th>Last name</th><th>House</th><th>Postcode</th><th>Date</th><th>Amount</th></tr>
            </thead>
            <tbody>
                @foreach ($claimable as $row)
                    <tr>
                        <td>{{ $row->firstName }}</td>
                        <td>{{ $row->lastName }}</td>
                        <td>{{ $row->house }}</td>
                        <td>{{ $row->postcode }}</td>
                        <td>{{ $row->date }}</td>
                        <td>{{ Money::format($row->payment->amount_pence) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>
            <a class="button button-primary" href="{{ route('gift-aid.csv', ['from' => $from, 'to' => $to]) }}">Download DRAFT schedule (CSV)</a>
        </p>
        <p class="hint">HMRC limits each schedule to 1,000 rows. Split longer periods.</p>
    @endif

    <h2>Needs attention ({{ count($needsAttention) }})</h2>
    @if ($needsAttention === [])
        <p class="hint">Nothing to fix.</p>
    @else
        <p class="hint">These payments are marked Gift Aid eligible but are left out of the download until they are fixed.</p>
        <table>
            <thead>
                <tr><th>Date</th><th>Amount</th><th>Donor</th><th>Problem</th><th><span class="visually-hidden">Actions</span></th></tr>
            </thead>
            <tbody>
                @foreach ($needsAttention as $row)
                    <tr>
                        <td>{{ UkDate::format($row->payment->received_on) }}</td>
                        <td>{{ Money::format($row->payment->amount_pence) }}</td>
                        <td>
                            @if ($row->payment->person)
                                <a href="{{ route('people.show', $row->payment->person) }}">{{ $row->payment->person->name }}</a>
                            @else
                                Anonymous
                            @endif
                        </td>
                        <td>{{ implode('; ', $row->problems) }}</td>
                        <td><a href="{{ route('payments.edit', $row->payment) }}">Edit payment</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
