<?php

namespace App\Http\Requests;

use App\Enums\DwaInspectionOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDwaInspectionRequest extends FormRequest
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
            'inspected_on' => ['required', 'date', 'before_or_equal:today'],
            'inspector' => ['nullable', 'string', 'max:255'],
            'outcome' => ['required', Rule::enum(DwaInspectionOutcome::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
