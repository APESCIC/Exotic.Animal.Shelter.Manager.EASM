<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organisation_name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', Rule::in(['en_GB', 'en_US'])],
            'timezone' => ['required', 'timezone:all'],
            'dwa_reminder_days' => ['sometimes', 'required', 'integer', 'between:7,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dwa_reminder_days.required' => 'Enter how many days before expiry to remind you about a DWA licence renewal.',
            'dwa_reminder_days.integer' => 'The DWA renewal reminder must be a whole number of days.',
            'dwa_reminder_days.between' => 'The DWA renewal reminder must be between 7 and 180 days before expiry.',
        ];
    }
}
