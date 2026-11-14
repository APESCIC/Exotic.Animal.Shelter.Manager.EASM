<?php

namespace Easm\Requirements;

/**
 * Probe helpers for the pre-install checker. Injectable so unit tests can
 * simulate ini values and paths without touching the real host.
 *
 * PHP 7.4 compatible — no Laravel.
 */
class HostEnvironment
{
    /** @var string */
    private $basePath;

    /** @var array<string, mixed>|null */
    private $iniOverrides;

    /** @var array<string, bool>|null */
    private $extensionOverrides;

    /** @var string|null */
    private $phpVersionOverride;

    /** @var array<string, bool>|null */
    private $writableOverrides;

    /** @var float|false|null */
    private $diskFreeOverride;

    /** @var bool|null */
    private $httpsOverride;

    /** @var bool|null */
    private $symlinkOverride;

    /** @var string|null */
    private $documentRootOverride;

    /** @var string|null */
    private $scriptFilenameOverride;

    /** @var bool|null */
    private $installedOverride;

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct($basePath, array $overrides = [])
    {
        $this->basePath = rtrim(str_replace('\\', '/', (string) $basePath), '/');
        $this->iniOverrides = isset($overrides['ini']) ? $overrides['ini'] : null;
        $this->extensionOverrides = isset($overrides['extensions']) ? $overrides['extensions'] : null;
        $this->phpVersionOverride = isset($overrides['php_version']) ? (string) $overrides['php_version'] : null;
        $this->writableOverrides = isset($overrides['writable']) ? $overrides['writable'] : null;
        $this->diskFreeOverride = array_key_exists('disk_free', $overrides) ? $overrides['disk_free'] : null;
        $this->httpsOverride = array_key_exists('https', $overrides) ? $overrides['https'] : null;
        $this->symlinkOverride = array_key_exists('symlink', $overrides) ? $overrides['symlink'] : null;
        $this->documentRootOverride = isset($overrides['document_root']) ? (string) $overrides['document_root'] : null;
        $this->scriptFilenameOverride = isset($overrides['script_filename']) ? (string) $overrides['script_filename'] : null;
        $this->installedOverride = array_key_exists('installed', $overrides) ? (bool) $overrides['installed'] : null;
    }

    /** @return string */
    public function basePath()
    {
        return $this->basePath;
    }

    /** @return string */
    public function phpVersion()
    {
        return $this->phpVersionOverride !== null ? $this->phpVersionOverride : PHP_VERSION;
    }

    /**
     * @param  string  $name
     * @return bool
     */
    public function extensionLoaded($name)
    {
        if ($this->extensionOverrides !== null && array_key_exists($name, $this->extensionOverrides)) {
            return (bool) $this->extensionOverrides[$name];
        }

        return extension_loaded($name);
    }

    /**
     * @param  string  $key
     * @return string
     */
    public function iniGet($key)
    {
        if ($this->iniOverrides !== null && array_key_exists($key, $this->iniOverrides)) {
            return (string) $this->iniOverrides[$key];
        }

        $value = ini_get($key);

        return $value === false ? '' : (string) $value;
    }

    /**
     * @param  string  $relativePath  Path relative to the app root
     * @return bool
     */
    public function isWritable($relativePath)
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($this->writableOverrides !== null && array_key_exists($relativePath, $this->writableOverrides)) {
            return (bool) $this->writableOverrides[$relativePath];
        }

        $full = $this->basePath.'/'.$relativePath;
        if (! file_exists($full)) {
            return false;
        }

        return is_writable($full);
    }

    /**
     * @return float|false
     */
    public function diskFreeBytes()
    {
        if ($this->diskFreeOverride !== null) {
            return $this->diskFreeOverride;
        }

        return @disk_free_space($this->basePath);
    }

    /** @return bool */
    public function canReachHttps()
    {
        if ($this->httpsOverride !== null) {
            return $this->httpsOverride;
        }

        if (! function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init('https://example.com/');
        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        return $errno === 0;
    }

    /** @return bool */
    public function canCreateSymlink()
    {
        if ($this->symlinkOverride !== null) {
            return $this->symlinkOverride;
        }

        if (! function_exists('symlink')) {
            return false;
        }

        $dir = $this->basePath.'/storage/framework';
        if (! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }

        $target = $dir.'/easm-symlink-target-'.uniqid('', true);
        $link = $dir.'/easm-symlink-link-'.uniqid('', true);
        @file_put_contents($target, 'ok');
        $ok = @symlink($target, $link);
        if (is_link($link) || file_exists($link)) {
            @unlink($link);
        }
        @unlink($target);

        return (bool) $ok;
    }

    /** @return string */
    public function documentRoot()
    {
        if ($this->documentRootOverride !== null) {
            return $this->documentRootOverride;
        }

        return isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']) : '';
    }

    /** @return string */
    public function scriptFilename()
    {
        if ($this->scriptFilenameOverride !== null) {
            return $this->scriptFilenameOverride;
        }

        return isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_FILENAME']) : '';
    }

    /** @return bool */
    public function isInstalled()
    {
        if ($this->installedOverride !== null) {
            return $this->installedOverride;
        }

        return is_file($this->basePath.'/storage/app/installed');
    }

    /**
     * Parse PHP ini size values such as 128M into bytes.
     *
     * @param  string  $value
     * @return int
     */
    public static function iniSizeToBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        if (! preg_match('/^([0-9]+)([KMG]?)$/i', $value, $match)) {
            return (int) $value;
        }

        $n = (int) $match[1];
        switch (strtoupper($match[2])) {
            case 'G':
                return $n * 1024 * 1024 * 1024;
            case 'M':
                return $n * 1024 * 1024;
            case 'K':
                return $n * 1024;
            default:
                return $n;
        }
    }
}
