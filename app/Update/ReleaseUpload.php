<?php

namespace App\Update;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Chunked manual upload of a release zip and .minisig (shared-host friendly).
 */
class ReleaseUpload
{
    public const MAX_CHUNK_BYTES = 4_000_000;

    public const KIND_PACKAGE = 'package';

    public const KIND_SIGNATURE = 'signature';

    public function __construct(
        private ?string $root = null,
    ) {
        $this->root = $root ?? ((string) config('easm.updates.state_path', storage_path('app/update'))).'/uploads';
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return array{upload_id: string, directory: string}
     */
    public function begin(): array
    {
        $id = now()->format('Ymd-His').'-'.Str::lower(Str::random(8));
        $directory = $this->directoryFor($id);
        File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/meta.json', json_encode([
            'id' => $id,
            'created_at' => now()->toIso8601String(),
            'package' => ['chunks' => 0, 'bytes' => 0, 'done' => false],
            'signature' => ['chunks' => 0, 'bytes' => 0, 'done' => false],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL);

        return ['upload_id' => $id, 'directory' => $directory];
    }

    /**
     * Append one chunk. Chunks must arrive in order starting at 0.
     *
     * @return array{kind: string, bytes: int, chunks: int, done: bool, path: string}
     */
    public function appendChunk(string $uploadId, string $kind, int $index, string $binary, bool $final = false): array
    {
        $kind = $this->assertKind($kind);
        if ($index < 0) {
            throw new UpdateException('Upload chunk index cannot be negative.');
        }
        if (strlen($binary) > self::MAX_CHUNK_BYTES) {
            throw new UpdateException('Upload chunk exceeds the '.self::MAX_CHUNK_BYTES.' byte limit.');
        }

        $directory = $this->directoryFor($uploadId);
        if (! is_dir($directory)) {
            throw new UpdateException('Unknown upload id.');
        }

        $meta = $this->readMeta($directory);
        $path = $directory.'/'.$this->filenameFor($kind);
        $expected = (int) ($meta[$kind]['chunks'] ?? 0);
        if ($index !== $expected) {
            throw new UpdateException('Upload chunk out of order (expected '.$expected.', got '.$index.').');
        }

        $mode = $index === 0 ? 'wb' : 'ab';
        $handle = fopen($path, $mode);
        if ($handle === false) {
            throw new UpdateException('Could not open the upload file for writing.');
        }
        try {
            $written = fwrite($handle, $binary);
        } finally {
            fclose($handle);
        }
        if ($written !== strlen($binary)) {
            throw new UpdateException('Incomplete write while saving the upload chunk.');
        }

        $meta[$kind]['chunks'] = $index + 1;
        $meta[$kind]['bytes'] = (int) filesize($path);
        $meta[$kind]['done'] = $final;
        $meta[$kind]['path'] = $path;
        $this->writeMeta($directory, $meta);

        return [
            'kind' => $kind,
            'bytes' => (int) $meta[$kind]['bytes'],
            'chunks' => (int) $meta[$kind]['chunks'],
            'done' => $final,
            'path' => $path,
        ];
    }

    /**
     * @return array{package_path: string, signature_path: string, upload_id: string}
     */
    public function finish(string $uploadId): array
    {
        $directory = $this->directoryFor($uploadId);
        $meta = $this->readMeta($directory);

        $package = $directory.'/'.$this->filenameFor(self::KIND_PACKAGE);
        $signature = $directory.'/'.$this->filenameFor(self::KIND_SIGNATURE);

        if (! is_file($package) || (int) ($meta['package']['bytes'] ?? 0) < 1) {
            throw new UpdateException('Package upload is incomplete.');
        }
        if (! is_file($signature) || (int) ($meta['signature']['bytes'] ?? 0) < 1) {
            throw new UpdateException('Signature upload is incomplete.');
        }

        $meta['package']['done'] = true;
        $meta['signature']['done'] = true;
        $meta['finished_at'] = now()->toIso8601String();
        $this->writeMeta($directory, $meta);

        return [
            'upload_id' => $uploadId,
            'package_path' => $package,
            'signature_path' => $signature,
        ];
    }

    private function directoryFor(string $uploadId): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $uploadId)) {
            throw new UpdateException('Invalid upload id.');
        }

        return $this->root.DIRECTORY_SEPARATOR.$uploadId;
    }

    private function assertKind(string $kind): string
    {
        if (! in_array($kind, [self::KIND_PACKAGE, self::KIND_SIGNATURE], true)) {
            throw new UpdateException('Upload kind must be package or signature.');
        }

        return $kind;
    }

    private function filenameFor(string $kind): string
    {
        return $kind === self::KIND_PACKAGE ? 'release.zip' : 'release.zip.minisig';
    }

    /**
     * @return array<string, mixed>
     */
    private function readMeta(string $directory): array
    {
        $path = $directory.'/meta.json';
        if (! is_file($path)) {
            throw new UpdateException('Upload metadata is missing.');
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function writeMeta(string $directory, array $meta): void
    {
        file_put_contents(
            $directory.'/meta.json',
            json_encode($meta, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL
        );
    }
}
