<?php

namespace Easm\Recovery;

use PDO;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Plain-PHP recovery actions when Laravel will not boot.
 */
class RecoveryActions
{
    /** @var string */
    private $basePath;

    /** @var string */
    private $updateRoot;

    /** @var string */
    private $backupRoot;

    /** @var list<string> */
    private $skipRelative = ['storage', '.git', 'node_modules'];

    public function __construct(string $basePath, ?string $updateRoot = null, ?string $backupRoot = null)
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->updateRoot = $updateRoot
            ? rtrim(str_replace('\\', '/', $updateRoot), '/')
            : $this->basePath.'/storage/app/update';
        $this->backupRoot = $backupRoot
            ? rtrim(str_replace('\\', '/', $backupRoot), '/')
            : $this->basePath.'/storage/app/backups';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function maintenanceOff(): array
    {
        $down = $this->basePath.'/storage/framework/down';
        if (is_file($down) && ! @unlink($down)) {
            return ['ok' => false, 'message' => 'Could not remove the maintenance file.'];
        }

        return ['ok' => true, 'message' => 'Maintenance mode is off.'];
    }

    /**
     * Accept the current tree: clear updater state and turn maintenance off.
     *
     * @return array{ok: bool, message: string}
     */
    public function finish(): array
    {
        $this->clearUpdateState();
        $result = $this->maintenanceOff();
        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'message' => 'Update session cleared and maintenance mode is off.'];
    }

    /**
     * Restore previous code + database backup, then clear state and maintenance.
     *
     * @return array{ok: bool, message: string}
     */
    public function rollback(): array
    {
        $previous = $this->updateRoot.'/previous';
        if (is_dir($previous)) {
            $this->copyTree($previous, $this->basePath);
        }

        $backupId = $this->backupIdFromState();
        if ($backupId === null) {
            $backupId = $this->newestReadyBackupId();
        }

        if ($backupId !== null) {
            $this->restoreDatabase($backupId);
            $this->restoreFilesZip($backupId);
        }

        $this->clearUpdateState();
        $maint = $this->maintenanceOff();
        if (! $maint['ok']) {
            return $maint;
        }

        return [
            'ok' => true,
            'message' => $backupId !== null
                ? 'Rolled back to the previous version using backup '.$backupId.'.'
                : 'Restored previous code (no database backup was available).',
        ];
    }

    private function clearUpdateState(): void
    {
        $state = $this->updateRoot.'/state.json';
        if (is_file($state)) {
            @unlink($state);
        }
        $lock = $this->updateRoot.'/update.lock';
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    private function backupIdFromState(): ?string
    {
        $statePath = $this->updateRoot.'/state.json';
        if (! is_file($statePath)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($statePath), true);
        if (! is_array($decoded)) {
            return null;
        }
        $id = $decoded['context']['backup_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function newestReadyBackupId(): ?string
    {
        if (! is_dir($this->backupRoot)) {
            return null;
        }
        $dirs = glob($this->backupRoot.'/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        foreach ($dirs as $dir) {
            $metaPath = $dir.'/meta.json';
            if (! is_file($metaPath)) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($meta) && ($meta['status'] ?? null) === 'ready' && is_file($dir.'/database.sql')) {
                return basename($dir);
            }
        }

        return null;
    }

    private function restoreDatabase(string $backupId): void
    {
        $sqlPath = $this->backupRoot.'/'.$backupId.'/database.sql';
        if (! is_file($sqlPath)) {
            throw new RuntimeException('Backup database.sql is missing.');
        }

        $env = EnvFile::parse($this->basePath.'/.env');
        $pdo = $this->pdoFromEnv($env);
        $sql = (string) file_get_contents($sqlPath);
        $statements = $this->splitStatements($sql);

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($statements as $statement) {
                $trimmed = $this->stripLeadingComments(trim($statement));
                if ($trimmed === '') {
                    continue;
                }
                $pdo->exec($trimmed);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @param  array<string, string>  $env
     */
    private function pdoFromEnv(array $env): PDO
    {
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3306';
        $database = $env['DB_DATABASE'] ?? '';
        $username = $env['DB_USERNAME'] ?? 'root';
        $password = $env['DB_PASSWORD'] ?? '';

        if ($database === '') {
            throw new RuntimeException('DB_DATABASE is missing from .env; cannot restore the database.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to MySQL for restore: '.$e->getMessage(), 0, $e);
        }

        return $pdo;
    }

    private function restoreFilesZip(string $backupId): void
    {
        $zipPath = $this->backupRoot.'/'.$backupId.'/files.zip';
        if (! is_file($zipPath) || ! class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return;
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
                    $target = $this->basePath.'/.env';
                } elseif (str_starts_with($name, 'storage/')) {
                    $target = $this->basePath.'/'.$name;
                } else {
                    continue;
                }
                $dir = dirname($target);
                if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                    continue;
                }
                file_put_contents($target, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    private function copyTree(string $source, string $destination): void
    {
        $source = rtrim(str_replace('\\', '/', $source), '/');
        $destination = rtrim(str_replace('\\', '/', $destination), '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($absolute, strlen($source)), '/');
            if ($relative === '' || $this->shouldSkip($relative)) {
                continue;
            }
            $target = $destination.'/'.$relative;
            if ($item->isDir()) {
                if (! is_dir($target) && ! mkdir($target, 0755, true) && ! is_dir($target)) {
                    throw new RuntimeException('Could not create '.$relative.' during recovery.');
                }

                continue;
            }
            $dir = dirname($target);
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new RuntimeException('Could not create directory for '.$relative);
            }
            if (! @copy($absolute, $target)) {
                throw new RuntimeException('Could not restore '.$relative);
            }
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

    /**
     * @return list<string>
     */
    private function splitStatements(string $contents): array
    {
        $out = [];
        $buffer = '';
        $inSingle = false;
        $length = strlen($contents);

        for ($i = 0; $i < $length; $i++) {
            $char = $contents[$i];

            if ($inSingle) {
                $buffer .= $char;
                if ($char === "'" && $i + 1 < $length && $contents[$i + 1] === "'") {
                    $buffer .= $contents[++$i];
                } elseif ($char === "'") {
                    $inSingle = false;
                }

                continue;
            }

            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;

                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $out[] = $trimmed;
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $out[] = $trimmed;
        }

        return $out;
    }

    private function stripLeadingComments(string $sql): string
    {
        while ($sql !== '' && str_starts_with($sql, '--')) {
            $newline = strpos($sql, "\n");
            if ($newline === false) {
                return '';
            }
            $sql = ltrim(substr($sql, $newline + 1));
        }

        return $sql;
    }
}
