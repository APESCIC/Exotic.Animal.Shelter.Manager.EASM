@php
    use App\Support\Money;
    use App\Support\UkDate;
@endphp
@extends('layouts.app')

@section('title', 'Ledger · '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <h1>Ledger</h1>
            <p class="hint">Donations, adoption fees and sponsorship payments received. Record each payment once, against a person and/or animal where known.</p>
        </div>
        @if (auth()->user()?->role?->canManageFinance())
            <div class="page-actions">
                <a class="button" href="{{ route('gift-aid.index') }}">Gift Aid</a>
                <a class="button button-primary" href="{{ route('payments.create') }}">Record payment</a>
            </div>
        @endif
    </div>

    <form class="filters" method="get" action="{{ route('finance.index') }}">
        <div>
            <label for="type">Type</label>
            <select id="type" name="type">
                <option value="">All types</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}" @selected($type === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="{{ $from }}">
        </div>
        <div>
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="{{ $to }}">
        </div>
        <div>
            <button type="submit">Filter</button>
        </div>
    </form>

    <div class="card-grid">
        <section class="card">
            <h2>Total</h2>
            <p class="stat">{{ Money::format($grandTotal) }}</p>
        </section>
        @foreach ($types as $option)
            @php $row = $totals->get($option->value); @endphp
            <section class="card">
                <h2>{{ $option->label() }}</h2>
                <p class="stat">{{ Money::format((int) ($row?->total ?? 0)) }}</p>
                <p class="hint">{{ (int) ($row?->payments ?? 0) }} {{ \Illuminate\Support\Str::plural('payment', (int) ($row?->payments ?? 0)) }}</p>
            </section>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>From</th>
                <th>Animal</th>
                <th>Method</th>
                <th>Gift Aid</th>
                <th><span class="visually-hidden">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ UkDate::format($payment->received_on) }}</td>
                    <td>{{ $payment->type->label() }}</td>
                    <td>{{ Money::format($payment->amount_pence) }}</td>
                    <td>
                        @if ($payment->person)
                            <a href="{{ route('people.show', $payment->person) }}">{{ $payment->person->name }}</a>
                        @else
                            Anonymous
                        @endif
                    </td>
                    <td>
                        @if ($payment->animal)
                            <a href="{{ route('animals.show', $payment->animal) }}">{{ $payment->animal->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $payment->method->label() }}</td>
                    <td>{{ $payment->gift_aid_eligible ? 'Yes' : 'No' }}</td>
                    <td>
                        @if (auth()->user()?->role?->canManageFinance())
                            <a href="{{ route('payments.edit', $payment) }}">Edit</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No payments match.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $payments->links() }}
@endsection
