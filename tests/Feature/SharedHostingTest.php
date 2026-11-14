<?php

namespace Tests\Feature;

use App\Support\SharedHosting;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedHostingTest extends TestCase
{
    public function test_root_htaccess_forbids_sensitive_paths(): void
    {
        $htaccess = (string) file_get_contents(base_path('.htaccess'));

        $this->assertFileExists(base_path('index.php'));
        $this->assertStringContainsString("require __DIR__.'/public/index.php';", (string) file_get_contents(base_path('index.php')));
        $this->assertStringContainsString('Prefer pointing the site document root at public/', $htaccess);
        $this->assertStringContainsString('[F,L]', $htaccess);

        foreach (SharedHosting::deniedRootPaths() as $path) {
            $this->assertTrue(
                SharedHosting::rootHtaccessForbids($path),
                $path.' should be forbidden by the root .htaccess'
            );
        }
        $this->assertMatchesRegularExpression('/composer\\\\?\.\(json\|lock\)|composer\.json/i', $htaccess);
    }

    public function test_public_storage_route_serves_uploads_without_symlink(): void
    {
        $path = 'animals/shared-hosting-'.bin2hex(random_bytes(4)).'.txt';
        Storage::disk('public')->put($path, 'photo-bytes');

        try {
            $response = $this->get('/storage/'.$path);
            $response->assertOk();
            $this->assertSame('photo-bytes', $response->streamedContent());
        } finally {
            Storage::disk('public')->delete($path);
        }
    }

    public function test_public_storage_route_rejects_traversal(): void
    {
        $this->get('/storage/../.env')->assertNotFound();
    }

    public function test_private_disk_does_not_claim_storage_url(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
        $this->assertSame(
            'App\Http\Controllers\PublicStorageController',
            app('router')->getRoutes()->getByName('storage.public')?->getActionName()
        );
        $this->assertNull(app('router')->getRoutes()->getByName('storage.local'));
    }

    public function test_app_temp_directory_is_inside_storage(): void
    {
        $path = SharedHosting::ensureAppTempDirectory();

        $this->assertDirectoryExists($path);
        $this->assertStringStartsWith(
            rtrim(str_replace('\\', '/', storage_path('framework')), '/'),
            str_replace('\\', '/', $path)
        );
        $this->assertSame($path, getenv('TMPDIR') ?: null);
    }

    public function test_open_basedir_allows_app_temp_and_storage(): void
    {
        $php = PHP_BINARY;
        $base = str_replace('\\', '/', base_path());
        $script = <<<'PHP'
<?php
$base = $argv[1];
ini_set('open_basedir', $base);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = App\Support\SharedHosting::ensureAppTempDirectory();
$file = $path.'/open-basedir-'.bin2hex(random_bytes(4)).'.txt';
if (file_put_contents($file, 'ok') === false) {
    fwrite(STDERR, "write failed\n");
    exit(2);
}
$read = file_get_contents($file);
@unlink($file);
echo $read === 'ok' ? "OK\n" : "BAD\n";
PHP;

        $tmpScript = storage_path('framework/tmp/open-basedir-test.php');
        if (! is_dir(dirname($tmpScript))) {
            mkdir(dirname($tmpScript), 0755, true);
        }
        file_put_contents($tmpScript, $script);

        $command = escapeshellarg($php).' '.escapeshellarg($tmpScript).' '.escapeshellarg($base);
        $output = [];
        $exit = 0;
        exec($command.' 2>&1', $output, $exit);
        @unlink($tmpScript);

        $this->assertSame(0, $exit, implode("\n", $output));
        $this->assertStringContainsString('OK', implode("\n", $output));
    }

    public function test_ensure_public_storage_command_runs(): void
    {
        $this->artisan('easm:ensure-public-storage')->assertSuccessful();
    }
}
