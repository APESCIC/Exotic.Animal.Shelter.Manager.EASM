<?php

namespace App\Enums;

enum AnimalMediaKind: string
{
    case Photo = 'photo';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Photo',
            self::Document => 'Document',
        };
    }
}
