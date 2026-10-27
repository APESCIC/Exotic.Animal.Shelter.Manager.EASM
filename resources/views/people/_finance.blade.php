@php
    use App\Support\Money;
    use App\Support\UkDate;

    $canManageFinance = (bool) auth()->user()?->role?->canManageFinance();
    $payments = $person->payments()->with('animal')->limit(20)->get();
    $declarations = $person->giftAidDeclarations;
    $sponsorships = $person->sponsorships()->with('animal')->get();
    $activeDeclaration = $declarations->first(fn ($declaration) => $declaration->isActive());
@endphp

<h2>Gift Aid</h2>
@if ($activeDeclaration)
    <p><x-badge tone="adoptable">Declaration in place</x-badge> covers donations from {{ UkDate::format($activeDeclaration->effective_from) }} ({{ $activeDeclaration->method->label() }}, declared {{ UkDate::format($activeDeclaration->declared_on) }}).</p>
@else
    <p class="hint">No active Gift Aid declaration.</p>
@endif
@if ($declarations->isNotEmpty())
    <table>
        <thead>
            <tr><th>Declared</th><th>Covers from</th><th>Cancelled</th><th>How</th><th><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
            @foreach ($declarations as $declaration)
                <tr>
                    <td>{{ UkDate::format($declaration->declared_on) }}</td>
                    <td>{{ UkDate::format($declaration->effective_from) }}</td>
                    <td>{{ $declaration->cancelled_on ? UkDate::format($declaration->cancelled_on) : '—' }}</td>
                    <td>{{ $declaration->method->label() }}</td>
                    <td>
                        @if ($canManageFinance && $declaration->isActive())
                            <form class="inline-form" method="post" action="{{ route('gift-aid-declarations.cancel', $declaration) }}"
                                  onsubmit="return window.confirm('Cancel this declaration from today?')">
                                @csrf
                                <button type="submit">Cancel</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
@if ($canManageFinance)
    <p><a class="button" href="{{ route('gift-aid-declarations.create', $person) }}">Record Gift Aid declaration</a></p>
@endif

<h2>Payments</h2>
@if ($payments->isEmpty())
    <p class="hint">No payments recorded.</p>
@else
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Animal</th><th>Gift Aid</th><th><span class="visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
            @foreach ($payments as $payment)
                <tr>
                    <td>{{ UkDate::format($payment->received_on) }}</td>
                    <td>{{ $payment->type->label() }}</td>
                    <td>{{ Money::format($payment->amount_pence) }}</td>
                    <td>{{ $payment->animal?->name ?? '—' }}</td>
                    <td>{{ $payment->gift_aid_eligible ? 'Yes' : 'No' }}</td>
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
