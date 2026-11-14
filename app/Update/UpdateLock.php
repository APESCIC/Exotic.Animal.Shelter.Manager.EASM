<?php

namespace App\Update;

/**
 * Single-run lock for the web updater (file flock + metadata).
 *
 * When a PHP request dies, the OS releases the flock so the next request can
 * continue. Metadata is best-effort for diagnostics only.
 */
class UpdateLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(
        private string $path,
        private int $staleAfterSeconds = 120,
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function acquire(string $owner): void
    {
        $this->ensureDirectory();

        $handle = fopen($this->path, 'c+b');
        if ($handle === false) {
            throw new UpdateException('Could not open the update lock file.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new UpdateException('Another update is already running.');
        }

        $payload = json_encode([
            'owner' => $owner,
            'acquired_at' => now()->toIso8601String(),
            'pid' => getmypid(),
            'stale_after_seconds' => $this->staleAfterSeconds,
        ], JSON_THROW_ON_ERROR);

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $payload.PHP_EOL);
        fflush($handle);

        $this->handle = $handle;
    }

    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        ftruncate($this->handle, 0);
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function isHeld(): bool
    {
        return is_resource($this->handle);
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        if (! is_file($this->path) || is_resource($this->handle)) {
            // Avoid reading while we hold an exclusive lock (Windows denies other opens).
            if (! is_resource($this->handle)) {
                return [];
            }

            try {
                $position = ftell($this->handle);
                rewind($this->handle);
                $raw = stream_get_contents($this->handle);
                if ($position !== false) {
                    fseek($this->handle, $position);
                }
                if (! is_string($raw) || trim($raw) === '') {
                    return [];
                }
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                return [];
            }
        }

        try {
            $raw = @file_get_contents($this->path);
            if (! is_string($raw) || trim($raw) === '') {
                return [];
            }
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update lock directory.');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
