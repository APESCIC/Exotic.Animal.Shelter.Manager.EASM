<?php

namespace App\Http\Requests;

use App\Enums\AnimalSex;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canManageAnimals();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', 'string', 'max:255'],
            'breed_type' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', Rule::enum(AnimalSex::class)],
            'date_of_birth' => ['nullable', 'date'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:200'],
            'colour' => ['nullable', 'string', 'max:255'],
            'identifying_code' => ['nullable', 'string', 'max:255'],
            'flags' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'bonded_animals' => ['nullable', 'string', 'max:255'],
            'entry_reason' => ['nullable', 'string', 'max:255'],
            'non_shelter' => ['sometimes', 'boolean'],
            'is_adoptable' => ['sometimes', 'boolean'],
            'deceased_at' => ['nullable', 'date'],
            'death_reason' => ['nullable', 'string', 'max:255'],
            'enclosure' => ['nullable', 'string', 'max:255'],
            'cites' => ['nullable', 'string', 'max:255'],
            'dwa' => ['nullable', 'string', 'max:255'],
            'primary_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'non_shelter' => $this->boolean('non_shelter'),
            'is_adoptable' => $this->boolean('is_adoptable'),
        ]);
    }
}
