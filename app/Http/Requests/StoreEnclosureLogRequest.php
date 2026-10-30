<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnclosureLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canRecordHusbandry() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reading = 'required_without_all:temperature_hot_c,temperature_cool_c,humidity_percent,notes';

        return [
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
            'temperature_hot_c' => [$reading, 'nullable', 'numeric', 'between:-20,80', 'decimal:0,1'],
            'temperature_cool_c' => [$reading, 'nullable', 'numeric', 'between:-20,80', 'decimal:0,1'],
            'humidity_percent' => [$reading, 'nullable', 'integer', 'between:0,100'],
            'notes' => [$reading, 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'temperature_hot_c.required_without_all' => 'Enter at least one reading or a note.',
            'temperature_cool_c.required_without_all' => 'Enter at least one reading or a note.',
            'humidity_percent.required_without_all' => 'Enter at least one reading or a note.',
            'notes.required_without_all' => 'Enter at least one reading or a note.',
            'recorded_at.before_or_equal' => 'The reading time cannot be in the future.',
        ];
    }
}
