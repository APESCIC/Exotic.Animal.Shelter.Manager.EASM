<?php

namespace App\Http\Requests;

use App\Install\CloudronDatabase;
use App\Install\DatabaseSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (config('easm.managed_database')) {
            return [];
        }

        $cloudron = app(CloudronDatabase::class)->available();
        $required = $cloudron ? 'required_if:db_source,'.DatabaseSource::Custom->value : 'required';

        $rules = [
            'db_host' => [$required, 'nullable', 'string', 'max:255'],
            'db_port' => [$required, 'nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => [$required, 'nullable', 'string', 'max:64'],
            'db_username' => [$required, 'nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];

        if ($cloudron) {
            $rules['db_source'] = ['required', Rule::in([DatabaseSource::Cloudron->value, DatabaseSource::Custom->value])];
        }

        return $rules;
    }
}
