<?php

namespace App\Http\Requests;

use App\Enums\AnimalSex;
use App\Enums\CitesAnnex;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Support\LookupField;
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
        $animal = $this->route('animal');
        $animal = $animal instanceof Animal ? $animal : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'species' => ['required', 'string', 'max:255'],
            'breed_type' => ['nullable', 'string', 'max:255', Rule::in(LookupField::allowedNames('breed_type', $animal?->breed_type))],
            'sex' => ['required', Rule::enum(AnimalSex::class)],
            'date_of_birth' => ['nullable', 'date'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:200'],
            'colour' => ['nullable', 'string', 'max:255', Rule::in(LookupField::allowedNames('colour', $animal?->colour))],
            'identifying_code' => ['nullable', 'string', 'max:255'],
            'flags' => ['nullable', 'array'],
            'flags.*' => ['string', 'max:255', Rule::in(LookupField::allowedNames('flags', $animal?->flags))],
            'location' => ['nullable', 'string', 'max:255'],
            'bonded_animals' => ['nullable', 'string', 'max:255'],
            'entry_reason' => ['nullable', 'string', 'max:255', Rule::in(LookupField::allowedNames('entry_reason', $animal?->entry_reason))],
            'non_shelter' => ['sometimes', 'boolean'],
            'is_adoptable' => ['sometimes', 'boolean'],
            'deceased_at' => ['nullable', 'date'],
            'death_reason' => ['nullable', 'string', 'max:255', Rule::in(LookupField::allowedNames('death_reason', $animal?->death_reason))],
            'enclosure_id' => ['nullable', 'integer', Rule::exists('enclosures', 'id')],
            'cites' => ['nullable', 'string', 'max:255'],
            'cites_annex' => ['nullable', Rule::enum(CitesAnnex::class)],
            'cites_exempt_annex_x' => ['sometimes', 'boolean'],
            'cites_exemption_note' => ['nullable', 'string', 'max:2000'],
            'dwa' => ['nullable', 'string', 'max:255'],
            'primary_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $flags = $this->input('flags', []);
        if (! is_array($flags)) {
            $flags = $flags === null || $flags === '' ? [] : [$flags];
        }

        $this->merge([
            'non_shelter' => $this->boolean('non_shelter'),
            'is_adoptable' => $this->boolean('is_adoptable'),
            'cites_exempt_annex_x' => $this->boolean('cites_exempt_annex_x'),
            'flags' => array_values(array_filter($flags, static fn ($v): bool => $v !== null && $v !== '')),
            'breed_type' => $this->blankToNull('breed_type'),
            'colour' => $this->blankToNull('colour'),
            'entry_reason' => $this->blankToNull('entry_reason'),
            'death_reason' => $this->blankToNull('death_reason'),
        ]);
    }

    private function blankToNull(string $key): ?string
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : (string) $value;
    }
}
