<?php

namespace Tests\Feature;

use App\Backup\BackupService;
use App\Install\ChunkedMigrator;
use App\Update\ReleaseSwapper;
use App\Update\UpdateRollback;
use App\Update\UpdateStateMachine;
use App\Update\UpdateStep;
use App\Update\WebUpdater;
use Easm\Package\SafeZipExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class UpdateRollbackTest extends TestCase
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

        $this->sandbox = storage_path('framework/testing/rollback-app-'.uniqid('', true));
        $this->workRoot = storage_path('framework/testing/rollback-work-'.uniqid('', true));
        $this->backupRoot = storage_path('framework/testing/rollback-backups-'.uniqid('', true));

        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::ensureDirectoryExists($this->sandbox.'/storage/framework');
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        File::ensureDirectoryExists($this->workRoot);
        File::ensureDirectoryExists($this->backupRoot);

        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/.env', "APP_KEY=base64:rollback-keep-me\n");
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
            $backups = new BackupService(root: $this->backupRoot);
            $swapper = new ReleaseSwapper;
            $rollback = new UpdateRollback(
                backups: $backups,
                swapper: $swapper,
                appRoot: $this->sandbox,
                workRoot: $this->workRoot,
            );

            return new WebUpdater(
                machine: new UpdateStateMachine($this->workRoot, 120),
                backups: $backups,
                migrator: $app->make(ChunkedMigrator::class),
                swapper: $swapper,
                rollback: $rollback,
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

    public function test_simulated_failure_at_every_step_restores_previous_version_and_health(): void
    {
        foreach (UpdateStep::sequence() as $step) {
            $this->runFailureScenario($step->value);
        }
    }

    private function runFailureScenario(string $failAtStep): void
    {
        foreach ([$this->sandbox, $this->workRoot, $this->backupRoot] as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }

        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::ensureDirectoryExists($this->sandbox.'/storage/framework');
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        File::ensureDirectoryExists($this->workRoot);
        File::ensureDirectoryExists($this->backupRoot);
        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/.env', "APP_KEY=base64:rollback-keep-me\n");
        file_put_contents($this->sandbox.'/storage/app/installed', "{\"version\":\"0.6.3\"}\n");

        $this->app->forgetInstance(WebUpdater::class);
        $this->app->forgetInstance(UpdateStateMachine::class);
        $this->app->forgetInstance(BackupService::class);

        [$package, $signature, $sha] = $this->buildSignedPackage('0.9.0');
        $updater = $this->app->make(WebUpdater::class);

        $updater->start('0.9.0', [
            'package_path' => $package,
            'signature_path' => $signature,
            'sha256' => $sha,
            'context' => [
                'fail_at_step' => $failAtStep,
            ],
        ]);

        $sawFailure = false;
        $guard = 0;
        do {
            $result = $updater->runNextStep('rollback-'.$failAtStep);
            $guard++;
            $this->assertLessThan(80, $guard, 'Update never finished for fail_at_step='.$failAtStep);

            if (isset($result['error'])) {
                $sawFailure = true;
                $this->assertTrue($result['rolled_back'] ?? false, 'Rollback did not succeed for '.$failAtStep.': '.json_encode($result['rollback'] ?? []));
                $this->assertTrue($result['rollback']['health_ok'] ?? false, 'Health not OK after rollback at '.$failAtStep);
                $this->assertTrue($result['rollback']['maintenance_off'] ?? false, 'Maintenance still on after rollback at '.$failAtStep);
                break;
            }
        } while (! ($result['done'] ?? false));

        $this->assertTrue($sawFailure, 'Expected a simulated failure at '.$failAtStep);
        $this->assertSame('0.6.3', trim((string) file_get_contents($this->sandbox.'/VERSION')));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame('failed', $updater->status()['status']);
        $this->assertTrue($updater->status()['context']['rolled_back'] ?? false);

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildSignedPackage(string $version): array
    {
        $dir = $this->workRoot.'/build';
        File::ensureDirectoryExists($dir.'/easm-'.$version);
        file_put_contents($dir.'/easm-'.$version.'/VERSION', $version."\n");
        file_put_contents($dir.'/easm-'.$version.'/UPDATE_MARKER', "swapped-from-{$version}\n");
        file_put_contents($dir.'/easm-'.$version.'/composer.json', "{\"name\":\"easm/test\"}\n");

        $zipPath = $this->workRoot.'/packages/easm-'.$version.'.zip';
        File::ensureDirectoryExists(dirname($zipPath));

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            $zip->addFile($dir.'/easm-'.$version.'/VERSION', 'easm-'.$version.'/VERSION');
            $zip->addFile($dir.'/easm-'.$version.'/UPDATE_MARKER', 'easm-'.$version.'/UPDATE_MARKER');
            $zip->addFile($dir.'/easm-'.$version.'/composer.json', 'easm-'.$version.'/composer.json');
            $zip->close();
        } else {
            $this->writeMinimalStoredZip($zipPath, [
                'easm-'.$version.'/VERSION' => $version."\n",
                'easm-'.$version.'/UPDATE_MARKER' => "swapped-from-{$version}\n",
                'easm-'.$version.'/composer.json' => "{\"name\":\"easm/test\"}\n",
            ]);
        }

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
