<?php

namespace App\Http\Requests;

use App\Models\Enclosure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnclosureRequest extends FormRequest
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
        $enclosure = $this->route('enclosure');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('enclosures', 'name')->ignore($enclosure instanceof Enclosure ? $enclosure->id : null),
            ],
            'zone' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function enclosureData(): array
    {
        return [
            ...$this->safe()->only(['name', 'zone', 'size', 'capacity', 'notes']),
            'active' => $this->boolean('active', true),
        ];
    }
}
