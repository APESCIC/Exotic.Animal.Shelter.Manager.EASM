<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnimalControlCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canManageAnimalControl();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reported_at' => ['required', 'date'],
            'location_area' => ['required', 'string', 'max:255'],
            'species' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reporter_person_id' => ['nullable', 'integer', 'exists:people,id'],
            'linked_animal_id' => ['nullable', 'integer', 'exists:animals,id'],
        ];
    }
}
