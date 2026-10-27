<?php

namespace App\Http\Requests;

use App\Enums\GiftAidDeclarationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGiftAidDeclarationRequest extends FormRequest
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
            'declared_on' => ['required', 'date'],
            'effective_from' => ['required', 'date'],
            'method' => ['required', Rule::enum(GiftAidDeclarationMethod::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
