<?php

namespace Tests\Feature;

use App\Backup\BackupService;
use App\Install\ChunkedMigrator;
use App\Support\SharedHosting;
use App\Update\UpdateStateMachine;
use App\Update\WebUpdater;
use Easm\Package\SafeZipExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves shared-hosting limits used by the constrained-host CI job.
 */
class ConstrainedHostMatrixTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    private string $workRoot;

    private string $backupRoot;

    private string $publicKeyPath;

    private string $secretKey;

    private string $keyId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension required');
        }

        $this->sandbox = storage_path('framework/testing/constrained-app-'.uniqid('', true));
        $this->workRoot = storage_path('framework/testing/constrained-work-'.uniqid('', true));
        $this->backupRoot = storage_path('framework/testing/constrained-backups-'.uniqid('', true));

        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::ensureDirectoryExists($this->sandbox.'/storage/framework');
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        File::ensureDirectoryExists($this->workRoot);
        File::ensureDirectoryExists($this->backupRoot);

        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/.env', "APP_KEY=base64:constrained-keep\n");
        file_put_contents($this->sandbox.'/storage/app/installed', "{\"version\":\"0.6.3\"}\n");

        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKeyBytes = sodium_crypto_sign_publickey($keypair);
        $this->keyId = random_bytes(8);
        $this->publicKeyPath = $this->workRoot.'/test.minisign.pub';
        file_put_contents(
            $this->publicKeyPath,
            "untrusted comment: minisign public key test\n".base64_encode('Ed'.$this->keyId.$publicKeyBytes)."\n"
        );

        config([
            'easm.updates.state_path' => $this->workRoot,
            'easm.updates.zip_backend' => SafeZipExtractor::BACKEND_PURE,
            'easm.backups.path' => $this->backupRoot,
            'easm.backups.keep' => 3,
        ]);

        $this->app->forgetInstance(UpdateStateMachine::class);
        $this->app->forgetInstance(BackupService::class);
        $this->app->forgetInstance(WebUpdater::class);

        $this->app->singleton(WebUpdater::class, function ($app) {
            return new WebUpdater(
                machine: new UpdateStateMachine($this->workRoot, 120),
                backups: new BackupService(root: $this->backupRoot),
                migrator: $app->make(ChunkedMigrator::class),
                extractor: new SafeZipExtractor(backend: SafeZipExtractor::BACKEND_PURE),
                appRoot: $this->sandbox,
                workRoot: $this->workRoot,
                publicKeyPath: $this->publicKeyPath,
            );
        });
    }

    protected function tearDown(): void
    {
        foreach ([$this->sandbox, $this->workRoot, $this->backupRoot] as $dir) {
            if (isset($dir) && is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        parent::tearDown();
    }

    public function test_php_ini_matches_shared_host_budget(): void
    {
        if (getenv('EASM_CONSTRAINED_HOST') !== '1') {
            $this->markTestSkipped('Set EASM_CONSTRAINED_HOST=1 in the constrained-host CI job.');
        }

        // PHPUnit uses a longer process limit; the shared-host request budget is 30s.
        $this->assertSame('30', (string) getenv('EASM_REQUEST_MAX_EXECUTION_TIME'));
        $requestLimit = trim((string) shell_exec(
            escapeshellarg(PHP_BINARY).' -d max_execution_time=30 -r "echo ini_get(\'max_execution_time\');"'
        ));
        $this->assertSame('30', $requestLimit);

        $memory = (string) ini_get('memory_limit');
        $this->assertTrue(
            strcasecmp($memory, '128M') === 0 || (int) $memory === 134217728,
            'memory_limit should be 128M, got '.$memory
        );
        $upload = (string) ini_get('upload_max_filesize');
        $this->assertTrue(
            strcasecmp($upload, '8M') === 0 || (int) $upload === 8388608,
            'upload_max_filesize should be 8M, got '.$upload
        );
        if (getenv('EASM_EXPECT_NO_ZIPARCHIVE') === '1') {
            $disabledClasses = strtolower((string) ini_get('disable_classes'));
            $this->assertStringContainsString('ziparchive', $disabledClasses);
        }
        if (getenv('EASM_EXPECT_NO_MYSQLDUMP') === '1') {
            $this->assertFalse($this->mysqldumpOnPath());
        }
        if (getenv('EASM_EXPECT_NO_SYMLINK') === '1') {
            $disabled = strtolower((string) ini_get('disable_functions'));
            $this->assertStringContainsString('symlink', $disabled);
        }
        $openBasedir = (string) ini_get('open_basedir');
        $this->assertNotSame('', $openBasedir);
        $this->assertStringContainsString(str_replace('\\', '/', base_path()), str_replace('\\', '/', $openBasedir));
    }

    public function test_update_from_previous_version_without_ziparchive_or_outbound_http(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Outbound HTTPS is blocked on this host.');
        });

        [$package, $signature, $sha] = $this->buildSignedPackagePurePhp('0.9.0');
        $updater = $this->app->make(WebUpdater::class);

        $started = microtime(true);
        $updater->start('0.9.0', [
            'package_path' => $package,
            'signature_path' => $signature,
            'sha256' => $sha,
        ]);

        $guard = 0;
        do {
            $result = $updater->runNextStep('constrained-host');
            $guard++;
            $this->assertLessThan(80, $guard);
            $this->assertArrayNotHasKey('error', $result, $result['message'] ?? 'step failed');
        } while (! ($result['done'] ?? false));

        $this->assertLessThan(30, microtime(true) - $started);
        $this->assertSame('0.9.0', trim((string) file_get_contents($this->sandbox.'/VERSION')));
        $this->assertSame('completed', $updater->status()['status']);
        $this->assertSame(SafeZipExtractor::BACKEND_PURE, $updater->status()['context']['extract_backend'] ?? null);
        $this->getJson('/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_repo_root_front_controller_forbids_sensitive_paths(): void
    {
        $this->assertFileExists(base_path('.htaccess'));
        $this->assertFileExists(base_path('index.php'));
        $this->assertStringContainsString(
            "require __DIR__.'/public/index.php';",
            (string) file_get_contents(base_path('index.php'))
        );
        foreach (SharedHosting::deniedRootPaths() as $path) {
            $this->assertTrue(
                SharedHosting::rootHtaccessForbids($path),
                $path.' should be forbidden when the document root is the repository root'
            );
        }
    }

    private function mysqldumpOnPath(): bool
    {
        $which = trim((string) shell_exec('command -v mysqldump 2>/dev/null || which mysqldump 2>/dev/null || echo'));

        return $which !== '';
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildSignedPackagePurePhp(string $version): array
    {
        $zipPath = $this->workRoot.'/packages/easm-'.$version.'.zip';
        File::ensureDirectoryExists(dirname($zipPath));
        $this->writeMinimalStoredZip($zipPath, [
            'easm-'.$version.'/VERSION' => $version."\n",
            'easm-'.$version.'/UPDATE_MARKER' => "swapped-from-{$version}\n",
            'easm-'.$version.'/composer.json' => "{\"name\":\"easm/test\"}\n",
        ]);

        $sha = hash_file('sha256', $zipPath);
        $this->assertIsString($sha);
        $sig = $this->signFile($zipPath);

        return [$zipPath, $sig, $sha];
    }

    private function signFile(string $filePath): string
    {
        $contents = (string) file_get_contents($filePath);
        $message = sodium_crypto_generichash($contents, '', 64);
        $signature = sodium_crypto_sign_detached($message, $this->secretKey);
        $trusted = 'timestamp:'.time()."\tfile:".basename($filePath);
        $global = sodium_crypto_sign_detached($signature.$trusted, $this->secretKey);

        $sigPath = $filePath.'.minisig';
        file_put_contents(
            $sigPath,
            "untrusted comment: signature from test secret key\n"
            .base64_encode('ED'.$this->keyId.$signature)."\n"
            .'trusted comment: '.$trusted."\n"
            .base64_encode($global)."\n"
        );

        return $sigPath;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function writeMinimalStoredZip(string $zipPath, array $files): void
    {
        $entries = '';
        $central = '';
        $offset = 0;
        $count = 0;
        foreach ($files as $name => $body) {
            $nameBytes = $name;
            $size = strlen($body);
            $crc = crc32($body);
            $local = pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($nameBytes), 0)
                .$nameBytes.$body;
            $entries .= $local;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($nameBytes), 0, 0, 0, 0, 0, $offset)
                .$nameBytes;
            $offset += strlen($local);
            $count++;
        }
        $end = pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($central), strlen($entries), 0);
        file_put_contents($zipPath, $entries.$central.$end);
    }
}
