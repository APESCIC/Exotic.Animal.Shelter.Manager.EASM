<?php

namespace App\Install;

use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallationState
{
    /**
     * Whether this shelter has already completed the web installer.
     */
    public function isInstalled(): bool
    {
        $markedInstalled = is_file($this->installedPath())
            || filter_var(config('easm.installed'), FILTER_VALIDATE_BOOLEAN);

        if (! $markedInstalled) {
            return false;
        }

        return $this->databaseSchemaReady();
    }

    /**
     * Whether core migration tables exist (install lock alone is not enough).
     */
    private function databaseSchemaReady(): bool
    {
        try {
            return Schema::hasTable('migrations');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Path to the environment file the installer may write.
     */
    public function envPath(): string
    {
        return (string) config('easm.env_path', base_path('.env'));
    }

    /**
     * Path to the install lock file.
     */
    public function installedPath(): string
    {
        return (string) config('easm.installed_path', storage_path('app/installed'));
    }

    /**
     * Record that installation finished and must not be repeated.
     */
    public function markInstalled(): void
    {
        $version = (string) config('app.version');
        $now = now()->timezone('UTC')->toIso8601String();

        $this->writeMarker([
            'installed_at' => $now,
            'installed_version' => $version,
            'updated_at' => $now,
            'updated_version' => $version,
            // Kept for older readers until the updater lands.
            'version' => $version,
        ]);
    }

    /**
     * Record that an update finished (installed_version stays as first install).
     */
    public function markUpdated(string $version): void
    {
        $existing = $this->readMarker();
        $now = now()->timezone('UTC')->toIso8601String();

        $this->writeMarker([
            'installed_at' => $existing['installed_at'] ?? $now,
            'installed_version' => $existing['installed_version'] ?? ($existing['version'] ?? $version),
            'updated_at' => $now,
            'updated_version' => $version,
            'version' => $version,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function readMarker(): array
    {
        $path = $this->installedPath();
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeMarker(array $payload): void
    {
        $path = $this->installedPath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create install lock directory [{$directory}].");
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL,
        );
    }
}
