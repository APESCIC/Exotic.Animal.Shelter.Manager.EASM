<?php

namespace Easm\Requirements;

/**
 * Runs the pre-install host checks. Plain PHP 7.4 — no Laravel.
 */
class RequirementsChecker
{
    const MIN_PHP = '8.3.0';

    const MIN_MEMORY = 128 * 1024 * 1024;

    const MIN_UPLOAD = 8 * 1024 * 1024;

    const MIN_DISK = 64 * 1024 * 1024;

    const MIN_EXECUTION_TIME = 30;

    /** @var array<int, string> */
    private static $requiredExtensions = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'gd',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_mysql',
        'tokenizer',
        'xml',
    ];

    /** @var HostEnvironment */
    private $env;

    public function __construct(HostEnvironment $env)
    {
        $this->env = $env;
    }

    /**
     * @return array{locked: bool, ok: bool, checks: array<int, array<string, string>>}
     */
    public function run()
    {
        if ($this->env->isInstalled()) {
            return [
                'locked' => true,
                'ok' => false,
                'checks' => [
                    $this->result(
                        'locked',
                        'fail',
                        'Already installed',
                        'This shelter already has an install lock at storage/app/installed.',
                        'Open the shelter sign-in page instead. To run checks again on a new host, use a fresh copy of EASM.'
                    ),
                ],
            ];
        }

        $checks = [];
        $checks[] = $this->checkPhpVersion();
        foreach (self::$requiredExtensions as $extension) {
            $checks[] = $this->checkExtension($extension, true);
        }
        $checks[] = $this->checkExtension('zip', false);
        $checks[] = $this->checkWritable('storage');
        $checks[] = $this->checkWritable('bootstrap/cache');
        $checks[] = $this->checkDocumentRoot();
        $checks[] = $this->checkOpenBasedir();
        $checks[] = $this->checkSymlink();
        $checks[] = $this->checkHttps();
        $checks[] = $this->checkMemoryLimit();
        $checks[] = $this->checkMaxExecutionTime();
        $checks[] = $this->checkUploadLimits();
        $checks[] = $this->checkDiskSpace();

        $ok = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $ok = false;
                break;
            }
        }

        return [
            'locked' => false,
            'ok' => $ok,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function checkPhpVersion()
    {
        $version = $this->env->phpVersion();
        if (version_compare($version, self::MIN_PHP, '>=')) {
            return $this->result(
                'php_version',
                'pass',
                'PHP version',
                'PHP '.$version.' meets the minimum of '.self::MIN_PHP.'.',
                ''
            );
        }

        return $this->result(
            'php_version',
            'fail',
            'PHP version',
            'PHP '.$version.' is too old. EASM needs PHP '.self::MIN_PHP.' or newer.',
            'In cPanel or Plesk, set MultiPHP / PHP version to 8.3 or 8.4 for this domain, then reload this page.'
        );
    }

    /**
     * @param  string  $name
     * @param  bool  $required
     * @return array<string, string>
     */
    private function checkExtension($name, $required)
    {
        $loaded = $this->env->extensionLoaded($name);
        if ($loaded) {
            return $this->result(
                'ext_'.$name,
                'pass',
                'PHP extension: '.$name,
                'The '.$name.' extension is loaded.',
                ''
            );
        }

        if (! $required) {
            return $this->result(
                'ext_'.$name,
                'warn',
                'PHP extension: '.$name.' (optional)',
                'ZipArchive is not available. Updates can still use the pure-PHP extractor, but zip is faster when present.',
                'Ask the host to enable the zip extension if they can. You can continue without it.'
            );
        }

        return $this->result(
            'ext_'.$name,
            'fail',
            'PHP extension: '.$name,
            'The '.$name.' extension is missing.',
            'Enable '.$name.' in the host PHP selector (MultiPHP INI Editor / PHP extensions), then reload this page.'
        );
    }

    /**
     * @param  string  $relative
     * @return array<string, string>
     */
    private function checkWritable($relative)
    {
        if ($this->env->isWritable($relative)) {
            return $this->result(
                'writable_'.str_replace('/', '_', $relative),
                'pass',
                'Writable: '.$relative.'/',
                $relative.'/ is writable by PHP.',
                ''
            );
        }

        return $this->result(
            'writable_'.str_replace('/', '_', $relative),
            'fail',
            'Writable: '.$relative.'/',
            $relative.'/ is missing or not writable by PHP.',
            'Create the folder if needed, then set permissions so the web user can write to it (often 775 on shared hosting).'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkDocumentRoot()
    {
        $docroot = rtrim($this->env->documentRoot(), '/');
        $script = $this->env->scriptFilename();
        $public = $this->env->basePath().'/public';

        if ($docroot !== '' && $this->pathsEqual($docroot, $public)) {
            return $this->result(
                'docroot',
                'pass',
                'Document root',
                'The site document root points at public/.',
                ''
            );
        }

        if ($script !== '' && strpos($script, '/public/') !== false) {
            return $this->result(
                'docroot',
                'pass',
                'Document root',
                'check.php is being served from public/.',
                ''
            );
        }

        return $this->result(
            'docroot',
            'fail',
            'Document root',
            'The web server does not appear to use public/ as the document root.',
            'In the host panel, set the domain document root to the public folder inside EASM (not the repository root), then reload.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkOpenBasedir()
    {
        $value = trim($this->env->iniGet('open_basedir'));
        if ($value === '') {
            return $this->result(
                'open_basedir',
                'pass',
                'open_basedir',
                'open_basedir is not set, so PHP can reach the app folders.',
                ''
            );
        }

        $base = $this->env->basePath();
        // Windows uses ';' (so 'C:/path' is not split on the drive colon); Unix uses ':'.
        $parts = preg_split('/'.preg_quote(PATH_SEPARATOR, '/').'/', $value) ?: [];
        foreach ($parts as $part) {
            $part = rtrim(str_replace('\\', '/', trim($part)), '/');
            if ($part !== '' && ($this->pathsEqual($part, $base) || strpos($base, $part.'/') === 0)) {
                return $this->result(
                    'open_basedir',
                    'pass',
                    'open_basedir',
                    'open_basedir includes the EASM folder.',
                    ''
                );
            }
        }

        return $this->result(
            'open_basedir',
            'fail',
            'open_basedir',
            'open_basedir is set to "'.$value.'" and does not include the EASM folder.',
            'Ask the host to add the EASM path to open_basedir, or clear open_basedir for this domain.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkSymlink()
    {
        if ($this->env->canCreateSymlink()) {
            return $this->result(
                'symlink',
                'pass',
                'Symlinks',
                'PHP can create symbolic links (needed for php artisan storage:link).',
                ''
            );
        }

        return $this->result(
            'symlink',
            'warn',
            'Symlinks',
            'PHP could not create a symbolic link here.',
            'On some Windows or locked-down hosts this is normal. The shared-hosting compatibility layer can fall back without symlinks; continue if other checks pass.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkHttps()
    {
        if ($this->env->canReachHttps()) {
            return $this->result(
                'https',
                'pass',
                'Outbound HTTPS',
                'PHP can open outbound HTTPS connections (for update checks and downloads).',
                ''
            );
        }

        return $this->result(
            'https',
            'warn',
            'Outbound HTTPS',
            'PHP could not reach the internet over HTTPS from this host.',
            'Allow outbound HTTPS for PHP, or use the manual release upload when updating. You can still install without outbound HTTPS.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkMemoryLimit()
    {
        $raw = $this->env->iniGet('memory_limit');
        $bytes = HostEnvironment::iniSizeToBytes($raw);
        if ($bytes === -1 || $bytes >= self::MIN_MEMORY) {
            return $this->result(
                'memory_limit',
                'pass',
                'memory_limit',
                'memory_limit is '.$raw.' (need at least 128M).',
                ''
            );
        }

        return $this->result(
            'memory_limit',
            'fail',
            'memory_limit',
            'memory_limit is '.$raw.', which is below 128M.',
            'Raise memory_limit to 128M or more in the host PHP settings, then reload.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkMaxExecutionTime()
    {
        $raw = $this->env->iniGet('max_execution_time');
        $seconds = (int) $raw;
        if ($seconds === 0 || $seconds >= self::MIN_EXECUTION_TIME) {
            return $this->result(
                'max_execution_time',
                'pass',
                'max_execution_time',
                'max_execution_time is '.$raw.' (0 means no limit).',
                ''
            );
        }

        return $this->result(
            'max_execution_time',
            'warn',
            'max_execution_time',
            'max_execution_time is '.$raw.' seconds; 30 or more is safer for install and updates.',
            'Raise max_execution_time to at least 30 in the host PHP settings if installs or updates time out.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkUploadLimits()
    {
        $uploadRaw = $this->env->iniGet('upload_max_filesize');
        $postRaw = $this->env->iniGet('post_max_size');
        $upload = HostEnvironment::iniSizeToBytes($uploadRaw);
        $post = HostEnvironment::iniSizeToBytes($postRaw);

        if ($upload >= self::MIN_UPLOAD && $post >= self::MIN_UPLOAD) {
            return $this->result(
                'upload_limits',
                'pass',
                'Upload limits',
                'upload_max_filesize is '.$uploadRaw.' and post_max_size is '.$postRaw.'.',
                ''
            );
        }

        return $this->result(
            'upload_limits',
            'warn',
            'Upload limits',
            'upload_max_filesize is '.$uploadRaw.' and post_max_size is '.$postRaw.'; 8M or more helps with photos and manual release uploads.',
            'Raise upload_max_filesize and post_max_size to at least 8M in the host PHP settings if uploads fail.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function checkDiskSpace()
    {
        $free = $this->env->diskFreeBytes();
        if ($free === false) {
            return $this->result(
                'disk_space',
                'warn',
                'Disk space',
                'Could not measure free disk space on this host.',
                'Check the hosting panel that the account has free space before installing.'
            );
        }

        if ($free >= self::MIN_DISK) {
            return $this->result(
                'disk_space',
                'pass',
                'Disk space',
                'About '.$this->formatBytes($free).' free on the app disk.',
                ''
            );
        }

        return $this->result(
            'disk_space',
            'fail',
            'Disk space',
            'Only about '.$this->formatBytes($free).' free; EASM needs at least 64M free to install comfortably.',
            'Free some space in the hosting account, then reload this page.'
        );
    }

    /**
     * @param  string  $id
     * @param  string  $status
     * @param  string  $label
     * @param  string  $detail
     * @param  string  $fix
     * @return array<string, string>
     */
    private function result($id, $status, $label, $detail, $fix)
    {
        return [
            'id' => $id,
            'status' => $status,
            'label' => $label,
            'detail' => $detail,
            'fix' => $fix,
        ];
    }

    /**
     * @param  string  $a
     * @param  string  $b
     * @return bool
     */
    private function pathsEqual($a, $b)
    {
        return rtrim(strtolower(str_replace('\\', '/', $a)), '/') === rtrim(strtolower(str_replace('\\', '/', $b)), '/');
    }

    /**
     * @param  float|int  $bytes
     * @return string
     */
    private function formatBytes($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 1).'G';
        }
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'M';
        }

        return round($bytes / 1024, 1).'K';
    }
}
