<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Submitted, self::UnderReview => true,
            self::Accepted, self::Rejected => false,
        };
    }
}
