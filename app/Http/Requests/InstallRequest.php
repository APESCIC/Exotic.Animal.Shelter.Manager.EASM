<?php

namespace App\Http\Requests;

use App\Install\CloudronDatabase;
use App\Install\DatabaseSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InstallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $database = [];

        if (! config('easm.managed_database')) {
            $cloudron = app(CloudronDatabase::class)->available();
            $required = $cloudron ? 'required_if:db_source,'.DatabaseSource::Custom->value : 'required';

            $database = [
                'db_host' => [$required, 'nullable', 'string', 'max:255'],
                'db_port' => [$required, 'nullable', 'integer', 'min:1', 'max:65535'],
                'db_database' => [$required, 'nullable', 'string', 'max:64'],
                'db_username' => [$required, 'nullable', 'string', 'max:255'],
                'db_password' => ['nullable', 'string', 'max:255'],
            ];

            if ($cloudron) {
                $database['db_source'] = ['required', Rule::in([DatabaseSource::Cloudron->value, DatabaseSource::Custom->value])];
            }
        }

        return [
            ...$database,
            'organisation' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }
}
