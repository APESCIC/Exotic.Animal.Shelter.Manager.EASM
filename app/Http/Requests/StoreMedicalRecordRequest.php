<?php

namespace App\Http\Requests;

use App\Enums\MedicalRecordType;
use App\Enums\UserRole;
use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canManageMedical();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MedicalRecordType::class)],
            'name' => ['required', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
            'given_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'stock_item_id' => ['nullable', 'integer', Rule::exists('stock_items', 'id')->where('active', true)],
            'stock_quantity' => ['nullable', 'required_with:stock_item_id', 'string', 'regex:'.StockItem::QUANTITY_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stock_quantity.required_with' => 'Enter how much stock was used.',
            'stock_quantity.regex' => 'Enter a number with up to 2 decimal places.',
            'stock_quantity.not_regex' => 'The quantity used must be more than 0.',
        ];
    }
}
