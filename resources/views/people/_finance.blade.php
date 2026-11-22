@php
    use App\Support\Money;
    use App\Support\UkDate;

    $canManageFinance = (bool) auth()->user()?->role?->canManageFinance();
    $payments = $person->payments()->with('animal')->limit(20)->get();
    $sponsorships = $person->sponsorships()->with('animal')->get();
@endphp

<h2>Payments</h2>
@if ($payments->isEmpty())
    <p class="hint">No payments recorded.</p>
@else
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Animal</th><th><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
            @foreach ($payments as $payment)
                <tr>
                    <td>{{ UkDate::format($payment->received_on) }}</td>
                    <td>{{ $payment->type->label() }}</td>
                    <td>{{ Money::format($payment->amount_pence) }}</td>
                    <td>{{ $payment->animal?->name ?? '—' }}</td>
                    <td>@if ($canManageFinance)<a href="{{ route('payments.edit', $payment) }}">Edit</a>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@if ($canManageFinance)
    <p><a class="button" href="{{ route('payments.create', ['person' => $person->id]) }}">Record payment</a></p>
@endif

<h2>Sponsorships</h2>
@if ($sponsorships->isEmpty())
    <p class="hint">Not sponsoring any animals.</p>
@else
    <table>
        <thead>
            <tr><th>Animal</th><th>Amount</th><th>From</th><th>Until</th></tr>
        </thead>
        <tbody>
            @foreach ($sponsorships as $sponsorship)
                <tr>
                    <td><a href="{{ route('animals.show', $sponsorship->animal) }}">{{ $sponsorship->animal->name }}</a></td>
                    <td>{{ Money::format($sponsorship->amount_pence) }} {{ strtolower($sponsorship->frequency->label()) }}</td>
                    <td>{{ UkDate::format($sponsorship->starts_on) }}</td>
                    <td>{{ $sponsorship->ends_on ? UkDate::format($sponsorship->ends_on) : 'Ongoing' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@if ($canManageFinance)
    <p><a class="button" href="{{ route('sponsorships.create', ['person' => $person->id]) }}">Add sponsorship</a></p>
@endif