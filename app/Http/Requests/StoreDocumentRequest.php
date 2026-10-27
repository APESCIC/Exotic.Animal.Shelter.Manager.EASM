<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageDocuments() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_template_id' => ['required', 'integer', Rule::exists('document_templates', 'id')->where('active', true)],
            'animal_id' => ['nullable', 'integer', 'exists:animals,id', 'required_without:person_id'],
            'person_id' => ['nullable', 'integer', 'exists:people,id', 'required_without:animal_id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'animal_id.required_without' => 'Choose an animal, a person, or both.',
            'person_id.required_without' => 'Choose an animal, a person, or both.',
        ];
    }
}
