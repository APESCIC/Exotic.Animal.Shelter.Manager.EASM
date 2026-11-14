<?php

namespace App\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Pure-PHP backups for the web updater: DB dump, files zip, retention, restore.
 */
class BackupService
{
    public const KEEP = 3;

    public function __construct(
        private ?DatabaseDumper $dumper = null,
        private ?DatabaseRestorer $restorer = null,
        private ?string $root = null,
        private ?int $keep = null,
    ) {
        $this->dumper ??= new DatabaseDumper;
        $this->restorer ??= new DatabaseRestorer;
        $this->root = $root ?? (string) config('easm.backups.path', storage_path('app/backups'));
        $this->keep = $keep ?? (int) config('easm.backups.keep', self::KEEP);
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return array{id: string, path: string}
     */
    public function start(): array
    {
        if (! is_dir($this->root) && ! mkdir($this->root, 0750, true) && ! is_dir($this->root)) {
            throw new BackupException('Could not create the backups directory.');
        }

        $id = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
        $path = $this->pathFor($id);
        if (! mkdir($path, 0750, true) && ! is_dir($path)) {
            throw new BackupException('Could not create a backup folder.');
        }

        file_put_contents($path.'/meta.json', json_encode([
            'id' => $id,
            'created_at' => now()->toIso8601String(),
            'driver' => DB::getDriverName(),
            'status' => 'started',
            'dump' => ['table_index' => 0, 'row_offset' => 0],
            'restore' => ['offset' => 0],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);

        file_put_contents($path.'/database.sql', "-- EASM backup {$id}\n");

        return ['id' => $id, 'path' => $path];
    }

    /**
     * @return array{done: bool, id: string, wrote: int, table: string|null}
     */
    public function dumpStep(string $id): array
    {
        $meta = $this->readMeta($id);
        $path = $this->pathFor($id);
        $result = $this->dumper->dumpChunk($path.'/database.sql', $meta['dump'] ?? []);
        $meta['dump'] = [
            'table_index' => $result['table_index'],
            'row_offset' => $result['row_offset'],
        ];
        $meta['status'] = $result['done'] ? 'dumped' : 'dumping';
        $this->writeMeta($id, $meta);

        return [
            'done' => $result['done'],
            'id' => $id,
            'wrote' => $result['wrote'],
            'table' => $result['table'],
        ];
    }

    /**
     * Zip .env and storage (excluding backups) into the backup folder.
     */
    public function archiveFiles(string $id): void
    {
        $path = $this->pathFor($id);
        $zipPath = $path.'/files.zip';

        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('ZipArchive is required to archive backup files.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Could not create the files archive.');
        }

        try {
            $env = base_path('.env');
            if (is_file($env) && is_readable($env)) {
                // addFromString avoids ZipArchive::close read errors with addFile on Windows.
                $zip->addFromString('.env', (string) file_get_contents($env));
            }

            $storage = storage_path();
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($storage, \FilesystemIterator::SKIP_DOTS)
            );
            $backupRoot = realpath($this->root) ?: $this->root;
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! $file->isReadable()) {
                    continue;
                }
                $absolute = $file->getPathname();
                $real = realpath($absolute) ?: $absolute;
                if (str_starts_with($real, $backupRoot)) {
                    continue;
                }
                $relative = 'storage/'.ltrim(str_replace('\\', '/', substr($absolute, strlen($storage))), '/');
                if (str_starts_with($relative, 'storage/app/backups/')
                    || str_starts_with($relative, 'storage/framework/testing/')
                    || str_starts_with($relative, 'storage/framework/cache/')
                    || str_starts_with($relative, 'storage/framework/sessions/')
                    || str_starts_with($relative, 'storage/logs/')) {
                    continue;
                }
                $contents = @file_get_contents($absolute);
                if ($contents === false) {
                    continue;
                }
                $zip->addFromString($relative, $contents);
            }
        } finally {
            $zip->close();
        }

        $meta = $this->readMeta($id);
        $meta['status'] = 'files_archived';
        $this->writeMeta($id, $meta);
    }

    public function finalize(string $id): void
    {
        $meta = $this->readMeta($id);
        $meta['status'] = 'ready';
        $meta['finished_at'] = now()->toIso8601String();
        $this->writeMeta($id, $meta);
        $this->prune();
    }

    /**
     * Create a full backup by looping dump steps (for updater sync callers / tests).
     */
    public function create(): string
    {
        $started = $this->start();
        $id = $started['id'];

        do {
            $step = $this->dumpStep($id);
        } while (! $step['done']);

        $this->archiveFiles($id);
        $this->finalize($id);

        return $id;
    }

    /**
     * @return array{done: bool, id: string, ran: int}
     */
    public function restoreStep(string $id): array
    {
        $meta = $this->readMeta($id);
        $path = $this->pathFor($id);
        $result = $this->restorer->restoreChunk($path.'/database.sql', $meta['restore'] ?? []);
        $meta['restore'] = ['offset' => $result['offset']];
        $meta['status'] = $result['done'] ? 'restored' : 'restoring';
        $this->writeMeta($id, $meta);

        return [
            'done' => $result['done'],
            'id' => $id,
            'ran' => $result['ran'],
        ];
    }

    public function restore(string $id): void
    {
        do {
            $step = $this->restoreStep($id);
        } while (! $step['done']);
    }

    /**
     * @return list<array{id: string, path: string, created_at: string|null, status: string|null, bytes: int}>
     */
    public function list(): array
    {
        if (! is_dir($this->root)) {
            return [];
        }

        $items = [];
        foreach (File::directories($this->root) as $directory) {
            $id = basename($directory);
            $metaPath = $directory.'/meta.json';
            $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : [];
            $items[] = [
                'id' => $id,
                'path' => $directory,
                'created_at' => is_array($meta) ? ($meta['created_at'] ?? null) : null,
                'status' => is_array($meta) ? ($meta['status'] ?? null) : null,
                'bytes' => $this->directoryBytes($directory),
            ];
        }

        usort($items, fn (array $a, array $b) => strcmp($b['id'], $a['id']));

        return $items;
    }

    public function prune(?int $keep = null): void
    {
        $keep ??= $this->keep ?? self::KEEP;
        $items = $this->list();
        foreach (array_slice($items, $keep) as $item) {
            File::deleteDirectory($item['path']);
        }
    }

    public function pathFor(string $id): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
            throw new BackupException('Invalid backup id.');
        }

        return $this->root.DIRECTORY_SEPARATOR.$id;
    }

    public function assertReady(string $id): string
    {
        $path = $this->pathFor($id);
        if (! is_dir($path) || ! is_file($path.'/meta.json')) {
            throw new BackupException('Backup not found.');
        }

        return $path;
    }

    /**
     * Build a downloadable zip of an existing backup folder.
     */
    public function downloadZip(string $id): string
    {
        $path = $this->assertReady($id);
        $target = $path.'.zip';

        if (! class_exists(ZipArchive::class)) {
            throw new BackupException('ZipArchive is required to download backups.');
        }

        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BackupException('Could not create the download zip.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($path) + 1);
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
            }
        } finally {
            $zip->close();
        }

        return $target;
    }

    /**
     * @return array<string, mixed>
     */
    private function readMeta(string $id): array
    {
        $path = $this->assertReady($id).'/meta.json';
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeMeta(string $id, array $meta): void
    {
        file_put_contents(
            $this->pathFor($id).'/meta.json',
            json_encode($meta, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL
        );
    }

    private function directoryBytes(string $directory): int
    {
        $total = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }

        return $total;
    }
}
