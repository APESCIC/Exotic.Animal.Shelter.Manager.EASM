<?php

declare(strict_types=1);

namespace Easm\Package;

/**
 * Safe zip extraction via ZipArchive or a pure-PHP fallback.
 *
 * Rejects zip-slip, absolute paths, symlinks and oversized archives.
 * Extraction can be resumed across HTTP requests by entry index.
 */
class SafeZipExtractor
{
    public const BACKEND_AUTO = 'auto';

    public const BACKEND_ZIPARCHIVE = 'ziparchive';

    public const BACKEND_PURE = 'pure';

    private int $maxUncompressedBytes;

    private int $maxEntries;

    private int $maxEntryBytes;

    private string $backend;

    public function __construct(
        int $maxUncompressedBytes = 209_715_200,
        int $maxEntries = 50_000,
        int $maxEntryBytes = 52_428_800,
        string $backend = self::BACKEND_AUTO
    ) {
        $this->maxUncompressedBytes = $maxUncompressedBytes;
        $this->maxEntries = $maxEntries;
        $this->maxEntryBytes = $maxEntryBytes;
        $this->backend = $backend;
    }

    /**
     * Extract every safe entry (single request helper).
     */
    public function extract(string $zipPath, string $targetDir): void
    {
        $state = ['next_index' => 0];
        do {
            $result = $this->extractChunk($zipPath, $targetDir, (int) $state['next_index'], $this->maxEntries);
            $state['next_index'] = $result['next_index'];
        } while (! $result['done']);
    }

    /**
     * Extract up to $maxEntriesPerCall entries starting at $startIndex.
     *
     * @return array{done: bool, next_index: int, extracted: int, backend: string}
     */
    public function extractChunk(
        string $zipPath,
        string $targetDir,
        int $startIndex = 0,
        int $maxEntriesPerCall = 100
    ): array {
        if ($startIndex < 0) {
            throw new PackageException('Extraction start index cannot be negative.');
        }
        if ($maxEntriesPerCall < 1) {
            throw new PackageException('Extraction chunk size must be at least 1.');
        }
        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            throw new PackageException('Could not create the extraction target directory.');
        }

        $backend = $this->resolveBackend();
        if ($backend === self::BACKEND_ZIPARCHIVE) {
            return $this->extractChunkZipArchive($zipPath, $targetDir, $startIndex, $maxEntriesPerCall);
        }

        return $this->extractChunkPure($zipPath, $targetDir, $startIndex, $maxEntriesPerCall);
    }

    public function resolveBackend(): string
    {
        if ($this->backend === self::BACKEND_PURE) {
            return self::BACKEND_PURE;
        }
        if ($this->backend === self::BACKEND_ZIPARCHIVE) {
            if (! class_exists(\ZipArchive::class)) {
                throw new PackageException('ZipArchive is not available.');
            }

            return self::BACKEND_ZIPARCHIVE;
        }
        if ($this->backend !== self::BACKEND_AUTO) {
            throw new PackageException('Unknown zip extraction backend.');
        }

        return class_exists(\ZipArchive::class) ? self::BACKEND_ZIPARCHIVE : self::BACKEND_PURE;
    }

    /**
     * @return array{done: bool, next_index: int, extracted: int, backend: string}
     */
    private function extractChunkZipArchive(
        string $zipPath,
        string $targetDir,
        int $startIndex,
        int $maxEntriesPerCall
    ): array {
        $zip = new \ZipArchive;
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new PackageException('Could not open the zip archive with ZipArchive.');
        }

        try {
            $total = $zip->numFiles;
            if ($total > $this->maxEntries) {
                throw new PackageException('Zip has too many entries.');
            }

            $extracted = 0;
            $index = $startIndex;
            $end = min($total, $startIndex + $maxEntriesPerCall);
            $bytesSoFar = $this->existingUncompressedBytes($targetDir);

            for (; $index < $end; $index++) {
                $stat = $zip->statIndex($index);
                if ($stat === false) {
                    throw new PackageException('Could not read zip entry metadata.');
                }

                $name = (string) $stat['name'];
                $opsys = \ZipArchive::OPSYS_DOS;
                $attr = 0;
                if (method_exists($zip, 'getExternalAttributesIndex')) {
                    $zip->getExternalAttributesIndex($index, $opsys, $attr);
                }

                if ($opsys === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0xF000) === 0xA000) {
                    throw new PackageException('Zip entry is a symbolic link.');
                }

                $isDir = str_ends_with($name, '/');
                $relative = ZipPathGuard::assertSafeEntryName($name);
                $destination = ZipPathGuard::assertDestinationInside($targetDir, $relative);

                if ($isDir) {
                    if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
                        throw new PackageException('Could not create a zip directory entry.');
                    }
                    $extracted++;

                    continue;
                }

                $size = (int) ($stat['size'] ?? 0);
                if ($size < 0 || $size > $this->maxEntryBytes) {
                    throw new PackageException('Zip entry exceeds the per-file size limit.');
                }
                $bytesSoFar += $size;
                if ($bytesSoFar > $this->maxUncompressedBytes) {
                    throw new PackageException('Zip uncompressed size exceeds the limit.');
                }

                $contents = $zip->getFromIndex($index);
                if ($contents === false) {
                    throw new PackageException('Could not read a zip entry.');
                }
                if (strlen($contents) !== $size) {
                    throw new PackageException('Zip entry size mismatch.');
                }

                if (file_exists($destination) || is_link($destination)) {
                    throw new PackageException('Refusing to overwrite an existing path during extraction.');
                }

                if (file_put_contents($destination, $contents) === false) {
                    throw new PackageException('Could not write an extracted file.');
                }
                $extracted++;
            }

            return [
                'done' => $index >= $total,
                'next_index' => $index,
                'extracted' => $extracted,
                'backend' => self::BACKEND_ZIPARCHIVE,
            ];
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{done: bool, next_index: int, extracted: int, backend: string}
     */
    private function extractChunkPure(
        string $zipPath,
        string $targetDir,
        int $startIndex,
        int $maxEntriesPerCall
    ): array {
        $reader = new PurePhpZipReader($zipPath);
        $entries = $reader->entries();
        $total = count($entries);
        if ($total > $this->maxEntries) {
            throw new PackageException('Zip has too many entries.');
        }

        $extracted = 0;
        $index = $startIndex;
        $end = min($total, $startIndex + $maxEntriesPerCall);
        $bytesSoFar = $this->existingUncompressedBytes($targetDir);

        for (; $index < $end; $index++) {
            $entry = $entries[$index];
            if (ZipPathGuard::isUnixSymlink($entry['external_attributes'], $entry['version_made_by'])) {
                throw new PackageException('Zip entry is a symbolic link.');
            }

            $relative = ZipPathGuard::assertSafeEntryName($entry['name']);
            $destination = ZipPathGuard::assertDestinationInside($targetDir, $relative);

            if ($entry['is_directory']) {
                if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
                    throw new PackageException('Could not create a zip directory entry.');
                }
                $extracted++;

                continue;
            }

            if ($entry['uncompressed_size'] > $this->maxEntryBytes) {
                throw new PackageException('Zip entry exceeds the per-file size limit.');
            }
            $bytesSoFar += $entry['uncompressed_size'];
            if ($bytesSoFar > $this->maxUncompressedBytes) {
                throw new PackageException('Zip uncompressed size exceeds the limit.');
            }

            $contents = $reader->readContents($entry);
            if (file_exists($destination) || is_link($destination)) {
                throw new PackageException('Refusing to overwrite an existing path during extraction.');
            }
            if (file_put_contents($destination, $contents) === false) {
                throw new PackageException('Could not write an extracted file.');
            }
            $extracted++;
        }

        return [
            'done' => $index >= $total,
            'next_index' => $index,
            'extracted' => $extracted,
            'backend' => self::BACKEND_PURE,
        ];
    }

    private function existingUncompressedBytes(string $targetDir): int
    {
        $real = realpath($targetDir);
        if ($real === false) {
            return 0;
        }

        $total = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }
}
