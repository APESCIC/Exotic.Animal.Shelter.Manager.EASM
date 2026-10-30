<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDwaLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageAnimals() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'licence_number' => ['required', 'string', 'max:255'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'species_covered' => ['nullable', 'string', 'max:2000'],
            'conditions' => ['nullable', 'string', 'max:10000'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['required', 'date', Rule::when($this->filled('issued_on'), 'after_or_equal:issued_on')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'animal_ids' => ['nullable', 'array'],
            'animal_ids.*' => ['integer', Rule::exists('animals', 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function licenceData(): array
    {
        return $this->safe()->only(['licence_number', 'issuing_authority', 'species_covered', 'conditions', 'issued_on', 'expires_on', 'notes']);
    }

    /**
     * @return list<int>
     */
    public function animalIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->safe()->input('animal_ids', []))));
    }
}
