<?php

namespace App\Update;

/**
 * Cached result of a GitHub Releases update check.
 */
final class UpdateStatus
{
    public function __construct(
        public readonly string $checkedAt,
        public readonly bool $ok,
        public readonly bool $updateAvailable,
        public readonly ?string $latestVersion = null,
        public readonly ?string $currentVersion = null,
        public readonly ?string $htmlUrl = null,
        public readonly ?string $notes = null,
        public readonly ?string $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            checkedAt: (string) ($data['checked_at'] ?? ''),
            ok: (bool) ($data['ok'] ?? false),
            updateAvailable: (bool) ($data['update_available'] ?? false),
            latestVersion: isset($data['latest_version']) ? (string) $data['latest_version'] : null,
            currentVersion: isset($data['current_version']) ? (string) $data['current_version'] : null,
            htmlUrl: isset($data['html_url']) ? (string) $data['html_url'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'checked_at' => $this->checkedAt,
            'ok' => $this->ok,
            'update_available' => $this->updateAvailable,
            'latest_version' => $this->latestVersion,
            'current_version' => $this->currentVersion,
            'html_url' => $this->htmlUrl,
            'notes' => $this->notes,
            'message' => $this->message,
        ];
    }

    public function shouldShowBanner(): bool
    {
        return $this->updateAvailable || (! $this->ok && filled($this->message));
    }
}
