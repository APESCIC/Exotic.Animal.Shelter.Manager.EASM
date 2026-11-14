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
        foreach (['/check.php', '/recover.php'] as $script) {
            if ($uri === $script && is_file($sitePath.'/public'.$script)) {
                $_SERVER['SCRIPT_FILENAME'] = $sitePath.'/public'.$script;
                $_SERVER['SCRIPT_NAME'] = $script;
                $_SERVER['PHP_SELF'] = $script;

                return $sitePath.'/public'.$script;
            }
        }

        return parent::frontControllerPath($sitePath, $siteName, $uri);
    }
}
