<?php

namespace Easm\Recovery;

/**
 * Random token that enables /recover.php only while an update is in progress.
 * Plain PHP — no Laravel.
 */
class RecoveryToken
{
    public const FILENAME = 'recovery.token';

    /** @var string */
    private $updateRoot;

    public function __construct(string $updateRoot)
    {
        $this->updateRoot = rtrim(str_replace('\\', '/', $updateRoot), '/');
    }

    public function path(): string
    {
        return $this->updateRoot.'/'.self::FILENAME;
    }

    public function issue(): string
    {
        if (! is_dir($this->updateRoot) && ! mkdir($this->updateRoot, 0750, true) && ! is_dir($this->updateRoot)) {
            throw new \RuntimeException('Could not create the update directory for the recovery token.');
        }

        $token = bin2hex(random_bytes(32));
        if (file_put_contents($this->path(), $token.PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write the recovery token.');
        }

        return $token;
    }

    public function read(): ?string
    {
        $path = $this->path();
        if (! is_file($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        return $value !== '' ? $value : null;
    }

    public function matches(?string $provided): bool
    {
        $expected = $this->read();
        if ($expected === null || $provided === null || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function clear(): void
    {
        $path = $this->path();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
