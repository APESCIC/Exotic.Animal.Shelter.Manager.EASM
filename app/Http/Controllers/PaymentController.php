<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Animal;
use App\Models\Payment;
use App\Models\Person;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $type = PaymentType::tryFrom($request->string('type')->toString());
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $query = Payment::query()
            ->receivedBetween($from !== '' ? $from : null, $to !== '' ? $to : null)
            ->when($type !== null, fn ($q) => $q->where('type', $type->value));

        $totals = (clone $query)
            ->selectRaw('type, sum(amount_pence) as total, count(*) as payments')
            ->groupBy('type')
            ->get()
            ->keyBy(fn (Payment $row): string => $row->type->value);

        return view('finance.index', [
            'payments' => $query->with(['person', 'animal'])
                ->orderByDesc('received_on')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'totals' => $totals,
            'grandTotal' => (int) $totals->sum('total'),
            'types' => PaymentType::cases(),
            'type' => $type,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();

        return view('finance.payments.create', $this->formData(new Payment([
            'type' => PaymentType::Donation,
            'method' => PaymentMethod::BankTransfer,
            'received_on' => today(),
            'person_id' => $request->integer('person') ?: null,
            'animal_id' => $request->integer('animal') ?: null,
        ])));
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $payment = Payment::query()->create($request->paymentData() + ['created_by' => $request->user()?->id]);

        return $this->redirectAfterSave($payment, 'Payment recorded.');
    }

    public function edit(Payment $payment): View
    {
        $this->authorizeManage();

        return view('finance.payments.edit', $this->formData($payment));
    }

    public function update(StorePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $payment->update($request->paymentData());

        return $this->redirectAfterSave($payment, 'Payment updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Payment $payment): array
    {
        return [
            'payment' => $payment,
            'amount' => $payment->amount_pence !== null ? Money::plain($payment->amount_pence) : '',
            'types' => PaymentType::cases(),
            'methods' => PaymentMethod::cases(),
            'people' => Person::query()->orderBy('name')->get(['id', 'name']),
            'animals' => Animal::query()->orderBy('name')->get(['id', 'name', 'species']),
        ];
    }

    private function redirectAfterSave(Payment $payment, string $status): RedirectResponse
    {
        $target = $payment->person_id !== null
            ? route('people.show', $payment->person_id)
            : route('finance.index');

        return redirect()->to($target)->with('status', $status);
    }

    private function authorizeView(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canViewFinance(), 403);
    }

    private function authorizeManage(): void
    {
        $role = request()->user()?->role;

        abort_unless($role !== null && $role->canManageFinance(), 403);
    }
}
