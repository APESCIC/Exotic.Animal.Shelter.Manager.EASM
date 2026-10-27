<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageFinance() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentType::class)],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'string', 'regex:'.Money::INPUT_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'received_on' => ['required', 'date'],
            'person_id' => ['nullable', 'integer', 'exists:people,id'],
            'animal_id' => ['nullable', 'integer', 'exists:animals,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'gift_aid_eligible' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount in pounds, for example 25 or 12.50.',
            'amount.not_regex' => 'The amount must be more than £0.00.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentData(): array
    {
        $type = PaymentType::from($this->string('type')->toString());

        return [
            'type' => $type,
            'method' => $this->string('method')->toString(),
            'amount_pence' => Money::toPence($this->string('amount')->toString()),
            'received_on' => $this->date('received_on'),
            'person_id' => $this->integer('person_id') ?: null,
            'animal_id' => $this->integer('animal_id') ?: null,
            'reference' => $this->input('reference'),
            'gift_aid_eligible' => $type->canClaimGiftAid() && $this->boolean('gift_aid_eligible'),
            'notes' => $this->input('notes'),
        ];
    }
}
