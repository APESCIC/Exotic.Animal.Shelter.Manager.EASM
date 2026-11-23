<?php

namespace App\Http\Requests;

use App\Enums\ObservationTypeKind;
use App\Models\ObservationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreObservationTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(ObservationTypeKind::class)],
            'lookup_list_id' => ['nullable', 'integer', Rule::exists('lookup_lists', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
            'species_filter' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $kind = ObservationTypeKind::tryFrom((string) $this->input('kind'));
            if ($kind?->usesLookupList() && ! $this->filled('lookup_list_id')) {
                $validator->errors()->add('lookup_list_id', 'Choose a lookup list for this observation type.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function observationTypeData(): array
    {
        return [
            'name' => trim((string) $this->input('name')),
            'kind' => ObservationTypeKind::from((string) $this->input('kind')),
            'lookup_list_id' => $this->filled('lookup_list_id') ? (int) $this->input('lookup_list_id') : null,
            'sort_order' => (int) ($this->input('sort_order') ?? 0),
            'active' => $this->boolean('active', true),
            'species_filter' => ObservationType::parseSpeciesFilterInput($this->input('species_filter')),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
            'lookup_list_id' => $this->filled('lookup_list_id') ? (int) $this->input('lookup_list_id') : null,
        ]);
    }
}
