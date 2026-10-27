<?php

namespace App\Services;

final readonly class DashboardAlert
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public int $count,
        public string $url,
        public string $tone,
    ) {}

    public function needsAttention(): bool
    {
        return $this->count > 0;
    }
}
