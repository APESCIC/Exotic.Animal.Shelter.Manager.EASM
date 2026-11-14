<?php

namespace App\Update;

use App\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Restores the previous code tree and database after a failed updater step.
 */
class UpdateRollback
{
    /**
     * @param  list<string>  $skipRelative
     */
    public function __construct(
        private BackupService $backups,
        private ReleaseSwapper $swapper = new ReleaseSwapper,
        private ?string $appRoot = null,
        private ?string $workRoot = null,
        private array $skipRelative = [
            'storage',
            '.git',
            'node_modules',
            '.playwright-mcp',
        ],
    ) {
        $this->appRoot ??= base_path();
        $this->workRoot ??= (string) config('easm.updates.state_path', storage_path('app/update'));
    }

    public function previousPath(): string
    {
        return $this->workRoot.DIRECTORY_SEPARATOR.'previous';
    }

    /**
     * Copy the live application (except storage / VCS) so swap can be undone.
     */
    public function snapshotLiveCode(): string
    {
        $target = $this->previousPath();
        if (is_dir($target)) {
            File::deleteDirectory($target);
        }
        File::ensureDirectoryExists($target);

        $appRoot = rtrim(str_replace('\\', '/', $this->appRoot), '/');
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($absolute, strlen($appRoot)), '/');
            if ($relative === '' || $this->shouldSkip($relative)) {
                continue;
            }

            $destination = $target.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            if (! @copy($absolute, $destination)) {
                throw new UpdateException('Could not snapshot '.$relative.' for rollback.');
            }
        }

        return $target;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, restored_code: bool, restored_database: bool, restored_files: bool, maintenance_off: bool, health_ok: bool, message: string, error: string|null}
     */
    public function run(array $context, string $failureMessage): array
    {
        $result = [
            'ok' => false,
            'restored_code' => false,
            'restored_database' => false,
            'restored_files' => false,
            'maintenance_off' => false,
            'health_ok' => false,
            'message' => '',
            'error' => null,
        ];

        try {
            $previous = $this->previousPath();
            $swapped = ! empty($context['swapped']) || is_dir($previous);
            if ($swapped && is_dir($previous)) {
                $this->swapper->swap($previous, $this->appRoot);
                $result['restored_code'] = true;
            }

            $backupId = (string) ($context['backup_id'] ?? '');
            if ($backupId !== '') {
                $this->backups->assertReady($backupId);
                $this->backups->restore($backupId);
                $result['restored_database'] = true;
                $this->restoreFilesFromBackup($backupId);
                $result['restored_files'] = true;
            }

            Artisan::call('up');
            $result['maintenance_off'] = true;

            DB::connection()->getPdo();
            $result['health_ok'] = true;
            $result['ok'] = true;
            $result['message'] = 'Rolled back after: '.$failureMessage;
        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = 'Rollback failed: '.$e->getMessage();
            try {
                Artisan::call('up');
                $result['maintenance_off'] = true;
            } catch (Throwable) {
                // Keep going; the original failure still stands.
            }
        }

        return $result;
    }

    private function restoreFilesFromBackup(string $backupId): void
    {
        $zipPath = $this->backups->pathFor($backupId).DIRECTORY_SEPARATOR.'files.zip';
        if (! is_file($zipPath)) {
            return;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new UpdateException('ZipArchive is required to restore backup files.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException('Could not open the backup files archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false || str_ends_with($name, '/')) {
                    continue;
                }
                $name = str_replace('\\', '/', $name);
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    continue;
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    continue;
                }

                if ($name === '.env') {
                    $target = $this->appRoot.DIRECTORY_SEPARATOR.'.env';
                } elseif (str_starts_with($name, 'storage/')) {
                    $target = $this->appRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                } else {
                    continue;
                }

                File::ensureDirectoryExists(dirname($target));
                file_put_contents($target, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    private function shouldSkip(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        foreach ($this->skipRelative as $name) {
            if ($relative === $name || str_starts_with($relative, $name.'/')) {
                return true;
            }
        }

        return false;
    }
}
