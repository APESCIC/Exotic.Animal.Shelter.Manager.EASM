<?php

namespace App\Http\Requests;

use App\Enums\DocumentTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentTemplateRequest extends FormRequest
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
            'type' => ['required', Rule::enum(DocumentTemplateType::class)],
            'body' => ['required', 'string', 'max:20000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
