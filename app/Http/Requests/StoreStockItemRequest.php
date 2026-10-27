<?php

namespace App\Http\Requests;

use App\Enums\StockCategory;
use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageStock() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(StockCategory::class)],
            'unit' => ['required', 'string', 'max:32'],
            'opening_quantity' => ['nullable', 'string', 'regex:'.StockItem::QUANTITY_PATTERN],
            'reorder_level' => ['nullable', 'string', 'regex:'.StockItem::QUANTITY_PATTERN],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_quantity.regex' => 'Enter a number with up to 2 decimal places.',
            'reorder_level.regex' => 'Enter a number with up to 2 decimal places.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemData(): array
    {
        return [
            ...$this->safe()->only(['name', 'category', 'unit', 'location', 'notes']),
            'reorder_level' => $this->filled('reorder_level') ? $this->string('reorder_level')->toString() : null,
            'active' => $this->boolean('active', true),
        ];
    }
}
