<?php

namespace App\Http\Requests;

use App\Enums\SponsorshipFrequency;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSponsorshipRequest extends FormRequest
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
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'animal_id' => ['required', 'integer', 'exists:animals,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'amount' => ['required', 'string', 'regex:'.Money::INPUT_PATTERN],
            'frequency' => ['required', Rule::enum(SponsorshipFrequency::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter the amount in pounds, for example 5 or 7.50.',
        ];
    }
}
