<?php

namespace App\Services;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\Animal;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use App\Support\LookupField;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomFieldSync
{
    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function formDefinitions(CustomFieldEntity $entity, bool $newRecord): Collection
    {
        return CustomFieldDefinition::query()
            ->active()
            ->where('entity', $entity->value)
            ->with('lookupList.values')
            ->ordered()
            ->get()
            ->when($newRecord, fn ($defs) => $defs->filter(fn (CustomFieldDefinition $d): bool => $d->show_on_new_record))
            ->values();
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public function overviewDefinitions(
        CustomFieldEntity $entity,
        Collection $valueDefinitionIds,
        ?string $species = null,
    ): Collection {
        return CustomFieldDefinition::query()
            ->where('entity', $entity->value)
            ->where(function ($query) use ($valueDefinitionIds): void {
                $query->where('active', true)
                    ->orWhereIn('id', $valueDefinitionIds);
            })
            ->ordered()
            ->get()
            ->filter(function (CustomFieldDefinition $definition) use ($species, $valueDefinitionIds): bool {
                if ($valueDefinitionIds->contains($definition->id)) {
                    return true;
                }

                return $definition->appliesToSpecies($species);
            })
            ->values();
    }

    public function syncForAnimal(Request $request, Animal $animal, ?string $species): void
    {
        $definitions = CustomFieldDefinition::query()
            ->active()
            ->forAnimal()
            ->with('lookupList')
            ->get()
            ->filter(fn (CustomFieldDefinition $definition): bool => $definition->appliesToSpecies($species));

        $this->sync($request, $definitions, animal: $animal);
    }

    public function syncForPerson(Request $request, Person $person): void
    {
        $definitions = CustomFieldDefinition::query()
            ->active()
            ->forPerson()
            ->with('lookupList')
            ->get();

        $this->sync($request, $definitions, person: $person);
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     */
    private function sync(
        Request $request,
        Collection $definitions,
        ?Animal $animal = null,
        ?Person $person = null,
    ): void {
        $submitted = $request->input('custom_fields', []);
        if (! is_array($submitted)) {
            $submitted = [];
        }

        $errors = [];

        foreach ($definitions as $definition) {
            $rawValue = $submitted[$definition->id] ?? ($definition->type === CustomFieldType::Boolean ? '0' : null);

            if ($definition->type->usesLookupList()) {
                $invalid = $this->invalidLookupNames($definition, $rawValue);
                if ($invalid !== []) {
                    $errors['custom_fields.'.$definition->id] = $definition->label.' must use values from its lookup list.';

                    continue;
                }
            }

            $value = $definition->normalizeStoredValue($rawValue);

            $query = $definition->values();
            if ($animal !== null) {
                $query->where('animal_id', $animal->id);
            } else {
                $query->where('person_id', $person?->id);
            }

            $existing = $query->first();

            if ($value === null || $value === '') {
                $existing?->delete();

                continue;
            }

            if ($existing !== null) {
                $existing->update(['value' => $value]);
            } elseif ($animal !== null) {
                $animal->customFieldValues()->create([
                    'definition_id' => $definition->id,
                    'value' => $value,
                ]);
            } else {
                $person?->customFieldValues()->create([
                    'definition_id' => $definition->id,
                    'value' => $value,
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return list<string>
     */
    private function invalidLookupNames(CustomFieldDefinition $definition, mixed $rawValue): array
    {
        $key = $definition->lookupList?->key;
        if ($key === null) {
            return ['missing-list'];
        }

        $names = $definition->type === CustomFieldType::MultiLookup
            ? (is_array($rawValue) ? $rawValue : ($rawValue === null || $rawValue === '' ? [] : [$rawValue]))
            : ($rawValue === null || $rawValue === '' ? [] : [$rawValue]);

        $allowed = LookupField::allowedNames($key, $names);
        $invalid = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '' && ! in_array($name, $allowed, true)) {
                $invalid[] = $name;
            }
        }

        return $invalid;
    }
}
