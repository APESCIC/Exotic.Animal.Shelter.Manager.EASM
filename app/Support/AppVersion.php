<?php

namespace App\Support;

/**
 * The app version from the VERSION file at the repository root.
 * config/app.php and GET /health use this as the source of truth unless APP_VERSION is set.
 */
class AppVersion
{
    public static function fromFile(?string $basePath = null): string
    {
        $path = ($basePath ?? base_path()).DIRECTORY_SEPARATOR.'VERSION';

        if (! is_file($path)) {
            return '';
        }

        return trim((string) file_get_contents($path));
    }

    /**
     * Prefer APP_VERSION when set (including empty string from env), otherwise the VERSION file.
     */
    public static function current(?string $basePath = null): string
    {
        $fromEnv = env('APP_VERSION');

        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        return self::fromFile($basePath);
    }
}
