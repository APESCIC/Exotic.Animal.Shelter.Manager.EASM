<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomFieldDefinitionRequest extends FormRequest
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
            'entity' => ['required', Rule::enum(CustomFieldEntity::class)],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'tooltip' => ['nullable', 'string', 'max:255'],
            'show_on_new_record' => ['sometimes', 'boolean'],
            'species_filter' => ['nullable', 'string', 'max:255'],
            'lookup_list_id' => ['nullable', 'integer', Rule::exists('lookup_lists', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = CustomFieldType::tryFrom((string) $this->input('type'));
            if ($type?->usesLookupList() && ! $this->filled('lookup_list_id')) {
                $validator->errors()->add('lookup_list_id', 'Choose a lookup list for this field type.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'is_mandatory' => $this->boolean('is_mandatory'),
            'show_on_new_record' => $this->boolean('show_on_new_record'),
            'species_filter' => $this->filled('species_filter') ? trim((string) $this->input('species_filter')) : null,
            'lookup_list_id' => $this->filled('lookup_list_id') ? (int) $this->input('lookup_list_id') : null,
        ]);
    }
}
