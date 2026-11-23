<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\AnimalObservation;
use Illuminate\Validation\Validator;

class UpdateAnimalObservationRequest extends StoreAnimalObservationRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;
        if (! $role instanceof UserRole || ! $role->canRecordObservations()) {
            return false;
        }

        $observation = $this->route('observation');

        return $observation instanceof AnimalObservation
            && $observation->canBeModifiedBy($this->user());
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
    }
}
