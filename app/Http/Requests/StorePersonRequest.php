<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldType;
use App\Enums\PersonCategory;
use App\Enums\UserRole;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canManagePeople();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(PersonCategory::class)],
            'category_custom' => ['nullable', 'string', 'max:255', 'required_if:category,custom'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'town_city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:255'],
            'banned' => ['sometimes', 'boolean'],
            'homechecked' => ['sometimes', 'boolean'],
            'flags' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isCreate = ! $this->route('person') instanceof Person;

            $definitions = CustomFieldDefinition::query()
                ->active()
                ->forPerson()
                ->get()
                ->filter(fn (CustomFieldDefinition $definition): bool => ! $isCreate || $definition->show_on_new_record);

            foreach ($definitions as $definition) {
                if (! $definition->is_mandatory) {
                    continue;
                }

                $raw = $this->input('custom_fields.'.$definition->id);
                $normalized = $definition->normalizeStoredValue(
                    $definition->type === CustomFieldType::Boolean ? ($raw ?? '0') : $raw,
                );

                $empty = $definition->type === CustomFieldType::Boolean
                    ? ($normalized !== '0' && $normalized !== '1')
                    : ($normalized === null || $normalized === '');

                if ($empty) {
                    $validator->errors()->add(
                        'custom_fields.'.$definition->id,
                        $definition->label.' is required.',
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'banned' => $this->boolean('banned'),
            'homechecked' => $this->boolean('homechecked'),
        ]);
    }
}
