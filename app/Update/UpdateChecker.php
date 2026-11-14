<?php

namespace App\Update;

use App\Support\AppVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Daily GitHub Releases check for admins (and optional web-cron), no shell/cron required.
 */
class UpdateChecker
{
    public const CACHE_STATUS = 'easm.updates.status';

    public const CACHE_CHECKED_AT = 'easm.updates.checked_at';

    public function __construct(
        private ?string $repository = null,
        private ?string $channel = null,
        private ?int $intervalHours = null,
    ) {
        $this->repository ??= (string) config('easm.updates.repository', 'APESCIC/Exotic.Animal.Shelter.Manager.EASM');
        $this->channel ??= (string) config('easm.updates.channel', 'stable');
        $this->intervalHours ??= max(1, (int) config('easm.updates.check_interval_hours', 24));
    }

    public function enabled(): bool
    {
        return (bool) config('easm.updates.enabled', true);
    }

    public function isDue(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $checkedAt = Cache::get(self::CACHE_CHECKED_AT);
        if (! is_string($checkedAt) || $checkedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($checkedAt)->lte(now()->subHours($this->intervalHours));
        } catch (Throwable) {
            return true;
        }
    }

    public function status(): ?UpdateStatus
    {
        $cached = Cache::get(self::CACHE_STATUS);
        if (! is_array($cached)) {
            return null;
        }

        return UpdateStatus::fromArray($cached);
    }

    /**
     * Run a check only when due (admin page loads / poor-man's scheduler).
     */
    public function maybeCheck(): ?UpdateStatus
    {
        if (! $this->isDue()) {
            return $this->status();
        }

        return $this->check();
    }

    /**
     * Fetch the latest release. Never throws; blocked networks store a quiet message.
     */
    public function check(bool $force = false): UpdateStatus
    {
        if (! $this->enabled() && ! $force) {
            return $this->status() ?? new UpdateStatus(
                checkedAt: now()->toIso8601String(),
                ok: true,
                updateAvailable: false,
                currentVersion: AppVersion::current(),
                message: 'Update checks are switched off.',
            );
        }

        if (! $force && ! $this->isDue()) {
            return $this->status() ?? $this->storeQuietFailure('No cached update status yet.');
        }

        $current = AppVersion::current();

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'EASM-UpdateCheck/'.$current,
                    'Accept' => 'application/vnd.github+json',
                ])
                ->get($this->releasesUrl());

            if (! $response->successful()) {
                return $this->storeQuietFailure('Could not reach GitHub Releases. Check manually.');
            }

            $payload = $response->json();
            if (! is_array($payload) || $payload === []) {
                return $this->storeQuietFailure('Could not read GitHub Releases. Check manually.');
            }

            // /releases/latest returns one object; /releases returns a list.
            $release = array_is_list($payload) ? $this->pickRelease($payload) : $payload;
            if ($release === null) {
                return $this->storeQuietFailure('No suitable release found. Check manually.');
            }

            $tag = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
            $latest = $tag !== '' ? $tag : null;
            $available = is_string($latest)
                && $current !== ''
                && version_compare($latest, $current, '>');

            $notes = isset($release['body']) ? trim((string) $release['body']) : null;
            if (is_string($notes) && strlen($notes) > 2000) {
                $notes = substr($notes, 0, 2000).'…';
            }

            $status = new UpdateStatus(
                checkedAt: now()->toIso8601String(),
                ok: true,
                updateAvailable: $available,
                latestVersion: $latest,
                currentVersion: $current,
                htmlUrl: isset($release['html_url']) ? (string) $release['html_url'] : $this->releasesHtmlUrl(),
                notes: $notes !== '' ? $notes : null,
                message: $available
                    ? 'A newer version is available.'
                    : 'You are on the latest '.$this->channel.' release.',
            );

            $this->remember($status);

            return $status;
        } catch (Throwable) {
            return $this->storeQuietFailure('Could not check for updates. Check manually.');
        }
    }

    private function releasesUrl(): string
    {
        $repo = trim($this->repository, '/');
        if ($this->channel === 'stable') {
            return "https://api.github.com/repos/{$repo}/releases/latest";
        }

        return "https://api.github.com/repos/{$repo}/releases?per_page=10";
    }

    private function releasesHtmlUrl(): string
    {
        return 'https://github.com/'.trim($this->repository, '/').'/releases';
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array<string, mixed>|null
     */
    private function pickRelease(array $releases): ?array
    {
        foreach ($releases as $release) {
            if (! is_array($release)) {
                continue;
            }
            if (($release['draft'] ?? false) === true) {
                continue;
            }
            if ($this->channel === 'stable' && ($release['prerelease'] ?? false) === true) {
                continue;
            }

            return $release;
        }

        return null;
    }

    private function storeQuietFailure(string $message): UpdateStatus
    {
        $status = new UpdateStatus(
            checkedAt: now()->toIso8601String(),
            ok: false,
            updateAvailable: false,
            currentVersion: AppVersion::current(),
            htmlUrl: $this->releasesHtmlUrl(),
            message: $message,
        );
        $this->remember($status);

        return $status;
    }

    private function remember(UpdateStatus $status): void
    {
        $ttl = now()->addDays(7);
        Cache::put(self::CACHE_STATUS, $status->toArray(), $ttl);
        Cache::put(self::CACHE_CHECKED_AT, $status->checkedAt, $ttl);
    }
}
