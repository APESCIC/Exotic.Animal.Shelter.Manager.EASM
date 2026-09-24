<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreAnimalMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role instanceof UserRole && $role->canManageMedia();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        $fail('A file upload is required.');

                        return;
                    }

                    $mime = (string) $value->getMimeType();
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    $isImage = str_starts_with($mime, 'image/')
                        && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
                    $isPdf = $mime === 'application/pdf' || $extension === 'pdf';

                    if (! $isImage && ! $isPdf) {
                        $fail('Only image or PDF uploads are allowed.');
                    }

                    if ($isImage && $value->getSize() > 5120 * 1024) {
                        $fail('Images may not be larger than 5 MB.');
                    }

                    if ($isPdf && $value->getSize() > 10240 * 1024) {
                        $fail('PDFs may not be larger than 10 MB.');
                    }
                },
            ],
        ];
    }
}
