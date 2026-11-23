<?php

namespace App\Http\Requests;

use App\Enums\ObservationTypeKind;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\ObservationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBulkAnimalObservationRequest extends FormRequest
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
            'enclosure_id' => ['nullable', 'integer', Rule::exists('enclosures', 'id')],
            'location' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array'],
            'rows.*.include' => ['sometimes', 'boolean'],
            'rows.*.types' => ['nullable', 'array'],
            'rows.*.types.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $enclosureId = $this->filled('enclosure_id') ? (int) $this->input('enclosure_id') : null;
            $location = trim((string) $this->input('location'));
            if ($enclosureId === null && $location === '') {
                $validator->errors()->add('enclosure_id', 'Choose an enclosure or enter a location.');
            }
        });
    }

    /**
     * @return list<array{animal: Animal, type: ObservationType, lookup_value_id: ?int, value_text: ?string}>
     */
    public function resolvedEntries(): array
    {
        $rows = $this->input('rows', []);
        if (! is_array($rows)) {
            return [];
        }

        $entries = [];
        foreach ($rows as $animalId => $row) {
            if (! is_array($row) || empty($row['include'])) {
                continue;
            }

            $animal = Animal::query()->find($animalId);
            if ($animal === null) {
                continue;
            }

            $types = ObservationType::activeForSpecies($animal->species);
            $typeInputs = is_array($row['types'] ?? null) ? $row['types'] : [];

            foreach ($types as $type) {
                if (! array_key_exists($type->id, $typeInputs)) {
                    continue;
                }

                $raw = $typeInputs[$type->id];
                if ($raw === null || $raw === '') {
                    continue;
                }

                $lookupId = null;
                $valueText = null;
                if ($type->kind === ObservationTypeKind::ValueList) {
                    $lookupId = (int) $raw;
                } else {
                    $valueText = trim((string) $raw);
                    if ($valueText === '') {
                        continue;
                    }
                }

                $entries[] = [
                    'animal' => $animal,
                    'type' => $type,
                    'lookup_value_id' => $lookupId,
                    'value_text' => $valueText,
                ];
            }
        }

        return $entries;
    }
}
