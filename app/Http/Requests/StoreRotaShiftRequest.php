<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRotaShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageRota() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'area' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.different' => 'The shift must end at a different time from when it starts.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->times();

                if ($start->diffInHours($end) > 16) {
                    $validator->errors()->add('end_time', 'A shift cannot be longer than 16 hours.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shiftData(): array
    {
        [$start, $end] = $this->times();

        return [
            'user_id' => $this->safe()->input('user_id') ?: null,
            'starts_at' => $start,
            'ends_at' => $end,
            'area' => $this->safe()->input('area') ?: null,
            'notes' => $this->safe()->input('notes') ?: null,
        ];
    }

    /**
     * An end time at or before the start time means the shift runs past midnight.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function times(): array
    {
        $date = $this->string('date')->toString();
        $start = Carbon::parse($date.' '.$this->string('start_time')->toString());
        $end = Carbon::parse($date.' '.$this->string('end_time')->toString());

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
