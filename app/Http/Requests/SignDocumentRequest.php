<?php

namespace App\Http\Requests;

use App\Rules\SignatureImage;
use Illuminate\Foundation\Http\FormRequest;

class SignDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageDocuments() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', new SignatureImage],
        ];
    }
}
