<?php

namespace App\Http\Requests;

use App\Enums\EquipmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnclosureEquipmentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(EquipmentType::class)],
            'name' => ['required', 'string', 'max:255'],
            'installed_on' => ['nullable', 'date', 'before_or_equal:today', 'required_with:replace_every_days'],
            'replace_every_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'installed_on.required_with' => 'Enter when it was fitted so EASM can work out when it is due.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function equipmentData(): array
    {
        return [
            ...$this->safe()->only(['type', 'name', 'installed_on', 'replace_every_days', 'notes']),
            'active' => $this->boolean('active', true),
        ];
    }
}
