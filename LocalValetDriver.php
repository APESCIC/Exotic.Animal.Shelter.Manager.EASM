<?php

/**
 * Herd / Valet driver: run public/check.php as its own front controller
 * (Valet would otherwise send every .php URI to public/index.php).
 */
class LocalValetDriver extends LaravelValetDriver
{
    /**
     * @param  string  $sitePath
     * @param  string  $siteName
     * @param  string  $uri
     * @return string
     */
    public function frontControllerPath($sitePath, $siteName, $uri)
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
