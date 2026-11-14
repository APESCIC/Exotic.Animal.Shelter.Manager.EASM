<?php

namespace App\Support;

/**
 * Helpers for locked-down shared hosting (root docroot, no symlink, open_basedir).
 */
final class SharedHosting
{
    /**
     * Paths that must return HTTP 403 when the document root is the repo root.
     *
     * @return list<string>
     */
    public static function deniedRootPaths(): array
    {
        return [
            '.env',
            'storage',
            'vendor',
            'composer.json',
        ];
    }

    /**
     * Absolute path for PHP temporary files, always inside the app tree.
     */
    public static function appTempPath(): string
    {
        return storage_path('framework/tmp');
    }

    /**
     * Point process temp env vars at storage/framework/tmp when possible.
     */
    public static function ensureAppTempDirectory(): string
    {
        $path = self::appTempPath();
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $real = realpath($path) ?: $path;
        putenv('TMPDIR='.$real);
        putenv('TMP='.$real);
        putenv('TEMP='.$real);
        $_ENV['TMPDIR'] = $real;
        $_ENV['TMP'] = $real;
        $_ENV['TEMP'] = $real;
        $_SERVER['TMPDIR'] = $real;
        $_SERVER['TMP'] = $real;
        $_SERVER['TEMP'] = $real;

        // May be ignored when the host forbids changing upload_tmp_dir at runtime.
        @ini_set('upload_tmp_dir', $real);

        return $real;
    }

    /**
     * True when public/storage is already a usable symlink or directory shim.
     */
    public static function publicStorageReady(): bool
    {
        $link = public_path('storage');

        if (is_link($link)) {
            $target = readlink($link);
            if ($target === false) {
                return false;
            }
            $resolved = $target;
            if (! self::isAbsolutePath($target)) {
                $resolved = dirname($link).DIRECTORY_SEPARATOR.$target;
            }

            return is_dir($resolved);
        }

        return is_dir($link);
    }

    /**
     * Prefer a symlink; if that fails, leave serving to the /storage route.
     *
     * @return array{linked: bool, method: string, message: string}
     */
    public static function ensurePublicStorage(): array
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        if (self::publicStorageReady()) {
            return [
                'linked' => true,
                'method' => is_link($link) ? 'symlink' : 'directory',
                'message' => 'Public storage is already available.',
            ];
        }

        if (file_exists($link) || is_link($link)) {
            return [
                'linked' => false,
                'method' => 'route',
                'message' => 'public/storage exists but is not a usable link; uploads are served through the application route.',
            ];
        }

        if (function_exists('symlink')) {
            $ok = @symlink($target, $link);
            if ($ok) {
                return [
                    'linked' => true,
                    'method' => 'symlink',
                    'message' => 'Created public/storage symlink.',
                ];
            }
        }

        return [
            'linked' => false,
            'method' => 'route',
            'message' => 'Could not create a symlink; uploads are served through the application /storage route instead.',
        ];
    }

    /**
     * Whether the root .htaccess forbids a relative path (best-effort parse).
     */
    public static function rootHtaccessForbids(string $relativePath): bool
    {
        $htaccess = base_path('.htaccess');
        if (! is_file($htaccess)) {
            return false;
        }

        $contents = (string) file_get_contents($htaccess);
        $needle = trim($relativePath, '/');

        foreach (self::deniedRootPaths() as $denied) {
            if (strcasecmp($denied, $needle) !== 0) {
                continue;
            }

            if (str_contains($contents, $denied) || str_contains(strtolower($contents), strtolower($denied))) {
                return true;
            }

            // composer.json is written as composer\.(json|lock) in the RewriteRule.
            if ($denied === 'composer.json' && str_contains($contents, 'composer\\.(json|lock)')) {
                return true;
            }

            return false;
        }

        return false;
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }
}
