<?php

namespace Tests\Unit\Requirements;

use Easm\Requirements\HostEnvironment;
use Easm\Requirements\RequirementsChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests — no Laravel application bootstrap beyond Composer autoload.
 */
class RequirementsCheckerTest extends TestCase
{
    public function test_requirements_code_does_not_reference_laravel_classes(): void
    {
        foreach ([
            dirname(__DIR__, 3).'/install/requirements/HostEnvironment.php',
            dirname(__DIR__, 3).'/install/requirements/RequirementsChecker.php',
            dirname(__DIR__, 3).'/public/check.php',
        ] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('Illuminate\\', $source, $path);
            $this->assertStringNotContainsString('App\\', $source, $path);
            $this->assertStringNotContainsString('Laravel\\', $source, $path);
            $this->assertStringNotContainsString('?->', $source, $path);
            $this->assertDoesNotMatchRegularExpression('/\bmatch\s*\(/', $source, $path);
        }
    }

    public function test_locked_when_already_installed(): void
    {
        $report = $this->checker(['installed' => true])->run();

        $this->assertTrue($report['locked']);
        $this->assertFalse($report['ok']);
        $this->assertSame('fail', $report['checks'][0]['status']);
        $this->assertSame('locked', $report['checks'][0]['id']);
    }

    public function test_php_too_old_is_reported_as_fail(): void
    {
        $report = $this->checker([
            'php_version' => '7.4.33',
            'installed' => false,
        ])->run();

        $php = $this->find($report, 'php_version');
        $this->assertSame('fail', $php['status']);
        $this->assertStringContainsString('too old', $php['detail']);
        $this->assertStringContainsString('8.3', $php['fix']);
    }

    public function test_php_8_3_passes(): void
    {
        $report = $this->readyHost(['php_version' => '8.3.0'])->run();
        $this->assertSame('pass', $this->find($report, 'php_version')['status']);
        $this->assertTrue($report['ok']);
    }

    #[DataProvider('iniCases')]
    public function test_ini_driven_checks(string $id, array $ini, string $expectedStatus): void
    {
        $report = $this->readyHost(['ini' => $ini])->run();
        $this->assertSame($expectedStatus, $this->find($report, $id)['status'], $id);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function iniCases(): array
    {
        return [
            'memory_ok' => ['memory_limit', ['memory_limit' => '256M'], 'pass'],
            'memory_low' => ['memory_limit', ['memory_limit' => '64M'], 'fail'],
            'memory_unlimited' => ['memory_limit', ['memory_limit' => '-1'], 'pass'],
            'exec_ok' => ['max_execution_time', ['max_execution_time' => '60'], 'pass'],
            'exec_unlimited' => ['max_execution_time', ['max_execution_time' => '0'], 'pass'],
            'exec_low' => ['max_execution_time', ['max_execution_time' => '10'], 'warn'],
            'upload_ok' => ['upload_limits', [
                'upload_max_filesize' => '16M',
                'post_max_size' => '16M',
            ], 'pass'],
            'upload_low' => ['upload_limits', [
                'upload_max_filesize' => '2M',
                'post_max_size' => '2M',
            ], 'warn'],
            'open_basedir_empty' => ['open_basedir', ['open_basedir' => ''], 'pass'],
        ];
    }

    public function test_missing_required_extension_fails(): void
    {
        $report = $this->readyHost([
            'extensions' => ['mbstring' => false],
        ])->run();

        $this->assertSame('fail', $this->find($report, 'ext_mbstring')['status']);
        $this->assertFalse($report['ok']);
    }

    public function test_optional_zip_warns_when_missing(): void
    {
        $report = $this->readyHost([
            'extensions' => ['zip' => false],
        ])->run();

        $this->assertSame('warn', $this->find($report, 'ext_zip')['status']);
        $this->assertTrue($report['ok']);
    }

    public function test_writable_and_disk_checks_use_overrides(): void
    {
        $failing = $this->readyHost([
            'writable' => ['storage' => false, 'bootstrap/cache' => true],
            'disk_free' => 1024,
        ])->run();

        $this->assertSame('fail', $this->find($failing, 'writable_storage')['status']);
        $this->assertSame('fail', $this->find($failing, 'disk_space')['status']);

        $passing = $this->readyHost([
            'writable' => ['storage' => true, 'bootstrap/cache' => true],
            'disk_free' => 200 * 1024 * 1024,
        ])->run();

        $this->assertSame('pass', $this->find($passing, 'writable_storage')['status']);
        $this->assertSame('pass', $this->find($passing, 'disk_space')['status']);
    }

    public function test_docroot_https_symlink_and_open_basedir_paths(): void
    {
        $base = str_replace('\\', '/', dirname(__DIR__, 3));
        $report = $this->readyHost([
            'document_root' => $base.'/public',
            'script_filename' => $base.'/public/check.php',
            'https' => true,
            'symlink' => true,
            'ini' => ['open_basedir' => $base],
        ])->run();

        $this->assertSame('pass', $this->find($report, 'docroot')['status']);
        $this->assertSame('pass', $this->find($report, 'https')['status']);
        $this->assertSame('pass', $this->find($report, 'symlink')['status']);
        $this->assertSame('pass', $this->find($report, 'open_basedir')['status']);
    }

    public function test_open_basedir_outside_app_fails(): void
    {
        $report = $this->readyHost([
            'ini' => ['open_basedir' => '/tmp'],
        ])->run();

        $this->assertSame('fail', $this->find($report, 'open_basedir')['status']);
    }

    public function test_ini_size_parser(): void
    {
        $this->assertSame(128 * 1024 * 1024, HostEnvironment::iniSizeToBytes('128M'));
        $this->assertSame(-1, HostEnvironment::iniSizeToBytes('-1'));
        $this->assertSame(2048, HostEnvironment::iniSizeToBytes('2K'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function checker(array $overrides): RequirementsChecker
    {
        return new RequirementsChecker(new HostEnvironment(dirname(__DIR__, 3), $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function readyHost(array $overrides = []): RequirementsChecker
    {
        $base = str_replace('\\', '/', dirname(__DIR__, 3));
        $extensions = [];
        foreach ([
            'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'json', 'mbstring',
            'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
        ] as $name) {
            $extensions[$name] = true;
        }

        $defaults = [
            'installed' => false,
            'php_version' => '8.3.12',
            'extensions' => $extensions,
            'writable' => ['storage' => true, 'bootstrap/cache' => true],
            'disk_free' => 500 * 1024 * 1024,
            'https' => true,
            'symlink' => true,
            'document_root' => $base.'/public',
            'script_filename' => $base.'/public/check.php',
            'ini' => [
                'memory_limit' => '256M',
                'max_execution_time' => '60',
                'upload_max_filesize' => '16M',
                'post_max_size' => '16M',
                'open_basedir' => '',
            ],
        ];

        if (isset($overrides['extensions'])) {
            $defaults['extensions'] = array_merge($defaults['extensions'], $overrides['extensions']);
            unset($overrides['extensions']);
        }
        if (isset($overrides['ini'])) {
            $defaults['ini'] = array_merge($defaults['ini'], $overrides['ini']);
            unset($overrides['ini']);
        }
        if (isset($overrides['writable'])) {
            $defaults['writable'] = array_merge($defaults['writable'], $overrides['writable']);
            unset($overrides['writable']);
        }

        return $this->checker(array_merge($defaults, $overrides));
    }

    /**
     * @param  array{checks: array<int, array<string, string>>}  $report
     * @return array<string, string>
     */
    private function find(array $report, string $id): array
    {
        foreach ($report['checks'] as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        $this->fail('Missing check '.$id);
    }
}
