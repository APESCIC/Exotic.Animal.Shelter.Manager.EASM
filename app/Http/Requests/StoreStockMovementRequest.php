<?php

namespace App\Http\Requests;

use App\Enums\StockMovementReason;
use App\Models\StockItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canRecordStockMovements() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['required', 'string', 'regex:'.StockItem::QUANTITY_PATTERN];

        if ($this->input('reason') !== StockMovementReason::Counted->value) {
            $rules[] = 'not_regex:/^0+(\.0+)?$/';
        }

        return [
            'reason' => ['required', Rule::enum(StockMovementReason::class)],
            'quantity' => $rules,
            'animal_id' => ['nullable', 'integer', 'exists:animals,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.regex' => 'Enter a number with up to 2 decimal places.',
            'quantity.not_regex' => 'The quantity must be more than 0.',
        ];
    }
}
