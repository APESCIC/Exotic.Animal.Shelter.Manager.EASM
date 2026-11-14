<?php

namespace Tests\Feature;

use App\Backup\BackupService;
use App\Install\ChunkedMigrator;
use App\Models\User;
use App\Update\ReleaseUpload;
use App\Update\UpdateStateMachine;
use App\Update\WebUpdater;
use Easm\Package\SafeZipExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class ReleaseUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    private string $workRoot;

    private string $backupRoot;

    private string $uploadRoot;

    private string $publicKeyPath;

    private string $secretKey;

    private string $keyId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('sodium extension required');
        }

        $this->sandbox = storage_path('framework/testing/upload-app-'.uniqid('', true));
        $this->workRoot = storage_path('framework/testing/upload-work-'.uniqid('', true));
        $this->backupRoot = storage_path('framework/testing/upload-backups-'.uniqid('', true));
        $this->uploadRoot = $this->workRoot.'/uploads';

        File::ensureDirectoryExists($this->sandbox.'/storage/app');
        File::ensureDirectoryExists($this->sandbox.'/storage/framework');
        File::ensureDirectoryExists($this->sandbox.'/bootstrap/cache');
        File::ensureDirectoryExists($this->workRoot);
        File::ensureDirectoryExists($this->backupRoot);
        File::ensureDirectoryExists($this->uploadRoot);

        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/.env', "APP_KEY=base64:keep-me\n");

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
        ]);

        $this->app->forgetInstance(UpdateStateMachine::class);
        $this->app->forgetInstance(BackupService::class);
        $this->app->forgetInstance(WebUpdater::class);
        $this->app->forgetInstance(ReleaseUpload::class);

        $this->app->singleton(ReleaseUpload::class, fn () => new ReleaseUpload($this->uploadRoot));
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

    public function test_update_succeeds_with_outbound_http_blocked_via_manual_upload(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Outbound HTTPS blocked');
        });

        [$packageBytes, $signatureBytes, $sha] = $this->buildSignedPackageBytes('0.9.0');
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $begin = $this->actingAs($admin)->postJson(route('admin.update.upload.begin'))->assertOk();
        $uploadId = $begin->json('upload_id');
        $this->assertNotEmpty($uploadId);

        $this->uploadInChunks($admin, $uploadId, 'package', $packageBytes);
        $this->uploadInChunks($admin, $uploadId, 'signature', $signatureBytes);

        $finished = $this->actingAs($admin)
            ->postJson(route('admin.update.upload.finish'), ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('sha256', $sha);

        $this->actingAs($admin)
            ->postJson(route('admin.update.start'), [
                'password' => 'password',
                'target_version' => '0.9.0',
                'package_path' => $finished->json('package_path'),
                'signature_path' => $finished->json('signature_path'),
                'sha256' => $sha,
            ])
            ->assertOk();

        $updater = $this->app->make(WebUpdater::class);
        $guard = 0;
        do {
            $result = $updater->runNextStep('upload-test');
            $this->assertArrayNotHasKey('error', $result, $result['message'] ?? 'step failed');
            $guard++;
            $this->assertLessThan(80, $guard);
        } while (! ($result['done'] ?? false));

        $this->assertSame('0.9.0', trim((string) file_get_contents($this->sandbox.'/VERSION')));
        $this->assertSame("APP_KEY=base64:keep-me\n", file_get_contents($this->sandbox.'/.env'));
        Http::assertNothingSent();
    }

    public function test_wrong_signature_is_rejected(): void
    {
        [$packageBytes, $signatureBytes, $sha] = $this->buildSignedPackageBytes('0.9.0');
        // Sign a different payload so verification against the real zip fails.
        $wrongSigPath = $this->signBytes('not-the-package-bytes', 'wrong.zip');
        $badSignature = (string) file_get_contents($wrongSigPath);

        $uploads = $this->app->make(ReleaseUpload::class);
        $begun = $uploads->begin();
        $uploads->appendChunk($begun['upload_id'], 'package', 0, $packageBytes, true);
        $uploads->appendChunk($begun['upload_id'], 'signature', 0, $badSignature, true);
        $paths = $uploads->finish($begun['upload_id']);

        $updater = $this->app->make(WebUpdater::class);
        $updater->start('0.9.0', [
            'package_path' => $paths['package_path'],
            'signature_path' => $paths['signature_path'],
            'sha256' => $sha,
        ]);

        $sawVerifyFailure = false;
        for ($i = 0; $i < 40; $i++) {
            $result = $updater->runNextStep('bad-sig');
            if (isset($result['error'])) {
                $sawVerifyFailure = true;
                $this->assertTrue(
                    str_contains(strtolower($result['error']), 'signature')
                    || str_contains(strtolower($result['error']), 'minisign')
                    || str_contains(strtolower($result['error']), 'checksum')
                    || str_contains(strtolower($result['error']), 'verify'),
                    $result['error']
                );
                break;
            }
            if ($result['done'] ?? false) {
                $this->fail('Update completed despite a wrong signature.');
            }
        }

        $this->assertTrue($sawVerifyFailure);
        $this->assertSame('0.6.3', trim((string) file_get_contents($this->sandbox.'/VERSION')));
    }

    public function test_chunk_over_limit_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $begin = $this->actingAs($admin)->postJson(route('admin.update.upload.begin'))->assertOk();
        $huge = str_repeat('a', ReleaseUpload::MAX_CHUNK_BYTES + 10);

        $this->actingAs($admin)
            ->post(route('admin.update.upload.chunk'), [
                'upload_id' => $begin->json('upload_id'),
                'kind' => 'package',
                'index' => 0,
                'final' => 1,
                'chunk' => UploadedFile::fake()->createWithContent('big.part', $huge),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    private function uploadInChunks(User $admin, string $uploadId, string $kind, string $bytes): void
    {
        $chunkSize = 1024;
        $index = 0;
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $slice = substr($bytes, $offset, $chunkSize);
            $final = ($offset + strlen($slice)) >= $length;
            $this->actingAs($admin)
                ->post(route('admin.update.upload.chunk'), [
                    'upload_id' => $uploadId,
                    'kind' => $kind,
                    'index' => $index,
                    'final' => $final ? 1 : 0,
                    'chunk' => UploadedFile::fake()->createWithContent($kind.'.part'.$index, $slice),
                ], ['Accept' => 'application/json'])
                ->assertOk();
            $offset += strlen($slice);
            $index++;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildSignedPackageBytes(string $version): array
    {
        $dir = $this->workRoot.'/build';
        File::ensureDirectoryExists($dir.'/easm-'.$version);
        file_put_contents($dir.'/easm-'.$version.'/VERSION', $version."\n");
        file_put_contents($dir.'/easm-'.$version.'/UPDATE_MARKER', "uploaded-{$version}\n");
        file_put_contents($dir.'/easm-'.$version.'/composer.json', "{\"name\":\"easm/test\"}\n");

        $zipPath = $this->workRoot.'/fixture-'.$version.'.zip';
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            $zip->addFile($dir.'/easm-'.$version.'/VERSION', 'easm-'.$version.'/VERSION');
            $zip->addFile($dir.'/easm-'.$version.'/UPDATE_MARKER', 'easm-'.$version.'/UPDATE_MARKER');
            $zip->addFile($dir.'/easm-'.$version.'/composer.json', 'easm-'.$version.'/composer.json');
            $zip->close();
        } else {
            $this->markTestSkipped('ZipArchive required to build the fixture zip');
        }

        $packageBytes = (string) file_get_contents($zipPath);
        $sha = hash('sha256', $packageBytes);
        $sigPath = $this->signBytes($packageBytes, basename($zipPath));
        $signatureBytes = (string) file_get_contents($sigPath);

        return [$packageBytes, $signatureBytes, $sha];
    }

    private function signBytes(string $contents, string $basename): string
    {
        $message = sodium_crypto_generichash($contents, '', 64);
        $signature = sodium_crypto_sign_detached($message, $this->secretKey);
        $trusted = 'timestamp:'.time()."\tfile:".$basename;
        $global = sodium_crypto_sign_detached($signature.$trusted, $this->secretKey);
        $sigPath = $this->workRoot.'/'.$basename.'.minisig';
        file_put_contents(
            $sigPath,
            "untrusted comment: signature from test secret key\n"
            .base64_encode('ED'.$this->keyId.$signature)."\n"
            .'trusted comment: '.$trusted."\n"
            .base64_encode($global)."\n"
        );

        return $sigPath;
    }
}
