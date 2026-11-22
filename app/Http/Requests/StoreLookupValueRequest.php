<?php

namespace App\Http\Requests;

use App\Models\LookupList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLookupValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var LookupList $list */
        $list = $this->route('lookupList');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('lookup_values', 'name')->where(
                    fn ($query) => $query->where('lookup_list_id', $list->id),
                ),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active', true),
        ]);
    }
}
