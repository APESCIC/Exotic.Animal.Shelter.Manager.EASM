<?php

namespace App\Http\Requests;

use App\Enums\CitesCertificateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCitesCertificateRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CitesCertificateType::class)],
            'certificate_number' => ['required', 'string', 'max:255'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
