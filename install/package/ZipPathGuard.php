<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * Shared path and entry policy for ZipArchive and pure-PHP extractors.
 */
final class ZipPathGuard
{
    public static function normalizeEntryName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = ltrim($name, '/');

        return $name;
    }

    public static function assertSafeEntryName(string $name): string
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new PackageException('Zip entry name is empty or contains a NUL byte.');
        }

        $raw = str_replace('\\', '/', $name);
        if (str_starts_with($raw, '/') || preg_match('#^[a-zA-Z]:/#', $raw) === 1 || str_starts_with($raw, '//')) {
            throw new PackageException('Zip entry uses an absolute path.');
        }

        $normalized = self::normalizeEntryName($name);
        if ($normalized === '') {
            throw new PackageException('Zip entry resolves to an empty path.');
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new PackageException('Zip entry path is unsafe (dot segments).');
            }
        }

        return $normalized;
    }

    public static function assertDestinationInside(string $targetDir, string $relativePath): string
    {
        $targetReal = realpath($targetDir);
        if ($targetReal === false) {
            throw new PackageException('Extraction target directory does not exist.');
        }

        $candidate = $targetReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $parent = dirname($candidate);
        if (! is_dir($parent)) {
            if (! mkdir($parent, 0755, true) && ! is_dir($parent)) {
                throw new PackageException('Could not create extraction directories.');
            }
        }

        $parentReal = realpath($parent);
        if ($parentReal === false || ! self::isInside($targetReal, $parentReal)) {
            throw new PackageException('Zip entry would extract outside the target directory.');
        }

        $final = $parentReal.DIRECTORY_SEPARATOR.basename($candidate);
        if (file_exists($final)) {
            $finalReal = realpath($final);
            if ($finalReal !== false && ! self::isInside($targetReal, $finalReal)) {
                throw new PackageException('Zip entry would extract outside the target directory.');
            }
        }

        return $final;
    }

    public static function isUnixSymlink(int $externalAttributes, int $versionMadeBy): bool
    {
        $host = ($versionMadeBy >> 8) & 0xFF;
        // 3 = Unix in the ZIP "version made by" host OS field.
        if ($host !== 3) {
            return false;
        }

        $mode = ($externalAttributes >> 16) & 0xFFFF;

        return ($mode & 0xF000) === 0xA000;
    }

    private static function isInside(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);

        return $path === $root || str_starts_with($path, $root.'/');
    }
}
