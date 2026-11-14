<?php

namespace App\Update;

/**
 * Atomic read/write of storage/app/update/state.json.
 */
class UpdateStateStore
{
    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            throw new UpdateException('Update state.json is corrupt.');
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function write(array $state): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update state directory.');
        }

        $state['updated_at'] = now()->toIso8601String();
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
        $temp = $this->path.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temp, $json) === false) {
            throw new UpdateException('Could not write the update state temporary file.');
        }

        if (! rename($temp, $this->path)) {
            @unlink($temp);
            // Windows may need replace-via-unlink when the target exists.
            if (is_file($this->path)) {
                @unlink($this->path);
            }
            if (! rename($temp, $this->path)) {
                @unlink($temp);
                throw new UpdateException('Could not replace the update state file.');
            }
        }
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
