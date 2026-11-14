<?php

namespace Tests\Feature;

use App\Backup\BackupService;
use App\Install\ChunkedMigrator;
use App\Models\User;
use App\Update\UpdateStateMachine;
use App\Update\WebUpdater;
use Easm\Package\SafeZipExtractor;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class WebUpdaterTest extends TestCase
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

        $this->sandbox = storage_path('framework/testing/updater-app-'.uniqid('', true));
        $this->workRoot = storage_path('framework/testing/updater-work-'.uniqid('', true));
        $this->backupRoot = storage_path('framework/testing/updater-backups-'.uniqid('', true));

        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::ensureDirectoryExists($this->sandbox.'/storage/framework');
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        File::ensureDirectoryExists($this->workRoot);
        File::ensureDirectoryExists($this->backupRoot);

        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/.env', "APP_KEY=base64:test-key-keep-me\n");
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

    public function test_full_update_completes_with_ziparchive_disabled_under_30_seconds(): void
    {
        $this->assertSame(SafeZipExtractor::BACKEND_PURE, config('easm.updates.zip_backend'));

        [$package, $signature, $sha] = $this->buildSignedPackage('0.9.0');
        $updater = $this->app->make(WebUpdater::class);

        $started = microtime(true);
        $updater->start('0.9.0', [
            'package_path' => $package,
            'signature_path' => $signature,
            'sha256' => $sha,
        ]);

        $guard = 0;
        do {
            $result = $updater->runNextStep('test-worker');
            $guard++;
            $this->assertLessThan(80, $guard, 'Update never finished.');
            $this->assertArrayNotHasKey('error', $result, $result['message'] ?? 'step failed');
        } while (! ($result['done'] ?? false));

        $elapsed = microtime(true) - $started;
        $this->assertLessThan(30, $elapsed, 'Full update exceeded 30 seconds.');

        $this->assertSame('0.9.0', trim((string) file_get_contents($this->sandbox.'/VERSION')));
        $this->assertSame("APP_KEY=base64:test-key-keep-me\n", file_get_contents($this->sandbox.'/.env'));
        $this->assertFileExists($this->sandbox.'/UPDATE_MARKER');
        $this->assertStringContainsString('swapped', (string) file_get_contents($this->sandbox.'/UPDATE_MARKER'));
        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertSame('completed', $updater->status()['status']);
        $this->assertSame(SafeZipExtractor::BACKEND_PURE, $updater->status()['context']['extract_backend'] ?? null);
    }

    public function test_non_admin_gets_403_and_csrf_middleware_is_enforced(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.update.index'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.update.start'), [
            'password' => 'password',
            'target_version' => '0.9.0',
        ])->assertForbidden();
        $this->actingAs($staff)->postJson(route('admin.update.step'))->assertForbidden();

        // Laravel skips CSRF while App::runningUnitTests() is true, so assert the
        // production middleware stack still includes ValidateCsrfToken (419 for bad tokens).
        $route = app('router')->getRoutes()->getByName('admin.update.start');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $csrfProtected = collect($middleware)->contains(
            fn ($name) => is_string($name) && (
                $name === 'web'
                || str_contains($name, 'ValidateCsrfToken')
                || str_contains($name, 'PreventRequestForgery')
                || str_contains($name, 'csrf')
            )
        );
        $this->assertTrue($csrfProtected, 'admin.update.start must sit behind CSRF (web stack).');

        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);
        $request = Request::create(route('admin.update.start'), 'POST', [
            'password' => 'password',
            'target_version' => '0.9.0',
        ], server: [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => $admin);

        // PHPUnit would skip CSRF; subclass so a missing token still 419s like production.
        $csrf = new class($this->app, $this->app->make('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        try {
            $csrf->handle($request, fn () => response('ok'));
            $this->fail('Expected TokenMismatchException for a missing CSRF token.');
        } catch (TokenMismatchException $e) {
            $response = $this->app->make(ExceptionHandler::class)
                ->render($request, $e);
            $this->assertSame(419, $response->getStatusCode());
        }
    }

    public function test_admin_can_start_with_password_and_run_steps(): void
    {
        [$package, $signature, $sha] = $this->buildSignedPackage('0.9.0');
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $this->actingAs($admin)
            ->postJson(route('admin.update.start'), [
                'password' => 'wrong',
                'target_version' => '0.9.0',
                'package_path' => $package,
                'signature_path' => $signature,
                'sha256' => $sha,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('admin.update.start'), [
                'password' => 'password',
                'target_version' => '0.9.0',
                'package_path' => $package,
                'signature_path' => $signature,
                'sha256' => $sha,
            ])
            ->assertOk()
            ->assertJsonPath('state.status', 'running');

        $this->actingAs($admin)
            ->postJson(route('admin.update.step'))
            ->assertOk()
            ->assertJsonPath('step', 'preflight');
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

        // Build zip with ZipArchive when available for the fixture only; extractor under test is pure-PHP.
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
        // Enough for PurePhpZipReader when ZipArchive is absent in the environment.
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
