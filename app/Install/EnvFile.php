<?php

namespace App\Install;

class EnvFile
{
    /**
     * Create or update keys in a .env file without removing other values.
     *
     * @param  array<string, scalar|null>  $values
     */
    public function write(string $path, array $values): void
    {
        if (! is_file($path)) {
            $example = base_path('.env.example');

            if (! is_file($example)) {
                throw new \RuntimeException('Missing .env.example; cannot create the environment file.');
            }

            $directory = dirname($path);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException("Unable to create environment directory [{$directory}].");
            }

            copy($example, $path);
        }

        $content = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->encode($value === null ? '' : (string) $value);
            $pattern = '/^'.preg_quote((string) $key, '/').'=.*/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $line, $content, 1);
            } else {
                $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $content);
    }

    /**
     * Remove keys from a .env file, leaving every other line as it was.
     *
     * @param  list<string>  $keys
     */
    public function remove(string $path, array $keys): void
    {
        if (! is_file($path)) {
            return;
        }

        $content = (string) file_get_contents($path);

        foreach ($keys as $key) {
            $content = (string) preg_replace('/^'.preg_quote($key, '/').'=.*(\R|$)/m', '', $content);
        }

        file_put_contents($path, $content);
    }

    private function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
