<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CloseAnimalControlCaseRequest extends FormRequest
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
            'close_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
