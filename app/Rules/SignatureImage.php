<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A PNG data URI drawn on the signature pad.
 */
class SignatureImage implements ValidationRule
{
    private const PREFIX = 'data:image/png;base64,';

    private const MAX_BYTES = 512_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::decode($value) === null) {
            $fail('Draw a signature before saving.');
        }
    }

    public static function decode(string $value): ?string
    {
        if (! str_starts_with($value, self::PREFIX)) {
            return null;
        }

        $bytes = base64_decode(substr($value, strlen(self::PREFIX)), true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            return null;
        }

        return $bytes;
    }
}
