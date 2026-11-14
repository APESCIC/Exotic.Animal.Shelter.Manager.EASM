<?php

use Valet\Drivers\LaravelValetDriver;

/**
 * Herd / Valet driver: run public/check.php as its own front controller
 * (Valet would otherwise send every .php URI to public/index.php).
 *
 * Herd's ValetDrivers live under Valet\Drivers\; LocalValetDriver itself
 * must stay in the global namespace (Valet loads it that way).
 */
class LocalValetDriver extends LaravelValetDriver
{
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        if ($uri === '/check.php' && is_file($sitePath.'/public/check.php')) {
            $_SERVER['SCRIPT_FILENAME'] = $sitePath.'/public/check.php';
            $_SERVER['SCRIPT_NAME'] = '/check.php';
            $_SERVER['PHP_SELF'] = '/check.php';

            return $sitePath.'/public/check.php';
        }

        return parent::frontControllerPath($sitePath, $siteName, $uri);
    }
}
