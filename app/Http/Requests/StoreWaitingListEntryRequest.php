<?php

namespace App\Http\Requests;

use App\Enums\WaitingListType;
use App\Enums\WaitingListUrgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaitingListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManagePeople() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(WaitingListType::class)],
            'urgency' => ['required', Rule::enum(WaitingListUrgency::class)],
            'person_id' => ['nullable', 'integer', 'exists:people,id', 'required_without:contact_name'],
            'contact_name' => ['nullable', 'string', 'max:255', 'required_without:person_id'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'species' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'added_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'person_id.required_without' => 'Choose a contact or type a name.',
            'contact_name.required_without' => 'Choose a contact or type a name.',
        ];
    }
}
