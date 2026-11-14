<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * Minimal ZIP writer (store only) without ext-zip / ZipArchive.
 */
class PurePhpZipWriter
{
    /**
     * @param  array<string, string>  $files  relative path => file contents
     */
    public static function write(string $zipPath, array $files): void
    {
        $dir = dirname($zipPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new PackageException('Could not create the zip directory.');
        }

        $entries = '';
        $central = '';
        $offset = 0;
        $count = 0;

        foreach ($files as $name => $body) {
            $name = str_replace('\\', '/', (string) $name);
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')) {
                throw new PackageException('Zip entry name is invalid: '.$name);
            }

            $size = strlen($body);
            $crc = crc32($body);
            $local = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0)
                .$name.$body;
            $entries .= $local;
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0,
                0,
                0,
                0,
                $crc,
                $size,
                $size,
                strlen($name),
                0,
                0,
                0,
                0,
                0,
                $offset
            ).$name;
            $offset += strlen($local);
            $count++;
        }

        $end = pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($central), strlen($entries), 0);
        if (file_put_contents($zipPath, $entries.$central.$end) === false) {
            throw new PackageException('Could not write the zip archive.');
        }
    }
}
