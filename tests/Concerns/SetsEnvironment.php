<?php

namespace Tests\Concerns;

trait SetsEnvironment
{
    /**
     * Run the callback with environment variables set (or unset when null), then restore them.
     *
     * @template T
     *
     * @param  array<string, string|null>  $values
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withEnvironment(array $values, callable $callback): mixed
    {
        $saved = [];

        foreach ($values as $key => $value) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $this->setEnvironment($key, $value, $value, $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($saved as $key => [$env, $server, $process]) {
                $this->setEnvironment($key, $env, $server, $process === false ? null : $process);
            }
        }
    }

    private function setEnvironment(string $key, ?string $env, ?string $server, ?string $process): void
    {
        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }

        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }

        putenv($process === null ? $key : $key.'='.$process);
    }
}
