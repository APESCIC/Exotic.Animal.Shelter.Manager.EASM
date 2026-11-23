<?php

namespace App\Http\Requests;

use App\Enums\ObservationTypeKind;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\ObservationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnimalObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canRecordObservations();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_on' => ['required', 'date'],
            'observation_type_id' => ['nullable', 'integer', Rule::exists('observation_types', 'id')],
            'lookup_value_id' => ['nullable', 'integer', Rule::exists('lookup_values', 'id')],
            'value_text' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Animal|null $animal */
            $animal = $this->route('animal');
            if (! $animal instanceof Animal) {
                return;
            }

            $types = ObservationType::activeForSpecies($animal->species);
            $typeId = $this->filled('observation_type_id') ? (int) $this->input('observation_type_id') : null;

            if ($types->isNotEmpty() && $typeId === null) {
                $body = trim((string) $this->input('body'));
                if ($body === '') {
                    $validator->errors()->add('observation_type_id', 'Choose an observation type or add notes for a legacy entry.');
                }

                return;
            }

            if ($typeId === null) {
                if (trim((string) $this->input('body')) === '') {
                    $validator->errors()->add('body', 'Enter what you observed.');
                }

                return;
            }

            $type = $types->firstWhere('id', $typeId);
            if ($type === null) {
                $validator->errors()->add('observation_type_id', 'This observation type does not apply to this animal.');

                return;
            }

            if ($type->kind === ObservationTypeKind::ValueList) {
                $lookupId = $this->filled('lookup_value_id') ? (int) $this->input('lookup_value_id') : null;
                if ($lookupId === null) {
                    $validator->errors()->add('lookup_value_id', 'Choose a value.');

                    return;
                }

                $listId = $type->lookup_list_id;
                $valid = $type->lookupList?->activeValues()->whereKey($lookupId)->exists() ?? false;
                if (! $valid) {
                    $validator->errors()->add('lookup_value_id', 'That value is not on this type\'s list.');
                }
            } elseif (trim((string) $this->input('value_text')) === '') {
                $validator->errors()->add('value_text', 'Enter a value.');
            }
        });
    }
}
