<?php

namespace Easm\Recovery;

/**
 * Minimal .env reader for recovery without Laravel.
 */
class EnvFile
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $vars = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (str_ends_with($value, $quote) && strlen($value) >= 2) {
                    $value = substr($value, 1, -1);
                }
            }
            $vars[$key] = $value;
        }

        return $vars;
    }
}
