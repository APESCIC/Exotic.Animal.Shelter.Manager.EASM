<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'town_city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'animal_id' => [
                'nullable',
                'integer',
                Rule::exists('animals', 'id')->where(function ($query): void {
                    $query->where('is_adoptable', true)
                        ->whereNull('deceased_at')
                        ->where('non_shelter', false);
                }),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('animal_id') && ! is_numeric($this->input('animal_id'))) {
            $this->merge(['animal_id' => null]);
        }
    }
}
