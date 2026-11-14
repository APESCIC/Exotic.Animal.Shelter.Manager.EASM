<?php

namespace Tests\Unit\Recovery;

use Easm\Recovery\RecoveryActions;
use Easm\Recovery\RecoveryToken;
use PHPUnit\Framework\TestCase;

/**
 * Plain recovery code — no Laravel application required for these assertions.
 */
class RecoveryPageTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir().'/easm-recovery-'.bin2hex(random_bytes(4));
        mkdir($this->sandbox.'/storage/app/update', 0777, true);
        mkdir($this->sandbox.'/storage/framework', 0777, true);
        mkdir($this->sandbox.'/install/recovery', 0777, true);
        mkdir($this->sandbox.'/public', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->sandbox);
        parent::tearDown();
    }

    public function test_recovery_sources_do_not_reference_laravel_or_vendor_autoload(): void
    {
        $root = dirname(__DIR__, 3);
        foreach ([
            $root.'/install/recovery/EnvFile.php',
            $root.'/install/recovery/RecoveryToken.php',
            $root.'/install/recovery/RecoveryActions.php',
            $root.'/public/recover.php',
        ] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('Illuminate\\', $source, $path);
            $this->assertStringNotContainsString('App\\', $source, $path);
            $this->assertStringNotContainsString('Laravel\\', $source, $path);
            $this->assertStringNotContainsString('vendor/autoload', $source, $path);
            $this->assertStringNotContainsString('require vendor', $source, $path);
        }
    }

    public function test_wrong_or_missing_token_gives_403(): void
    {
        $this->seedRecoverScript();
        $token = new RecoveryToken($this->sandbox.'/storage/app/update');
        $token->issue();

        $missing = $this->runRecover([]);
        $this->assertSame(403, $missing['code']);
        $this->assertStringContainsString('Forbidden', $missing['body']);

        $wrong = $this->runRecover(['token' => 'not-the-token']);
        $this->assertSame(403, $wrong['code']);
        $this->assertStringContainsString('Forbidden', $wrong['body']);
    }

    public function test_token_removed_after_successful_maintenance_off(): void
    {
        $this->seedRecoverScript();
        $token = new RecoveryToken($this->sandbox.'/storage/app/update');
        $value = $token->issue();
        file_put_contents($this->sandbox.'/storage/framework/down', json_encode(['time' => time()]));

        $result = $this->runRecover([
            'token' => $value,
            'action' => 'maintenance_off',
        ], 'POST');

        $this->assertSame(200, $result['code']);
        $this->assertStringContainsString('recovery token has been removed', $result['body']);
        $this->assertFileDoesNotExist($token->path());
        $this->assertFileDoesNotExist($this->sandbox.'/storage/framework/down');
    }

    public function test_works_with_vendor_deliberately_broken(): void
    {
        $this->seedRecoverScript();
        mkdir($this->sandbox.'/vendor', 0777, true);
        file_put_contents($this->sandbox.'/vendor/autoload.php', "<?php throw new Exception('vendor broken');\n");

        $token = new RecoveryToken($this->sandbox.'/storage/app/update');
        $value = $token->issue();
        file_put_contents($this->sandbox.'/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/storage/app/update/state.json', json_encode([
            'status' => 'failed',
            'context' => [],
        ]));

        // Prove vendor would blow up if loaded.
        try {
            require $this->sandbox.'/vendor/autoload.php';
            $this->fail('Expected broken vendor autoload to throw.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('vendor broken', $e->getMessage());
        }

        $result = $this->runRecover([
            'token' => $value,
            'action' => 'finish',
        ], 'POST');

        $this->assertSame(200, $result['code']);
        $this->assertStringContainsString('Update session cleared', $result['body']);
        $this->assertFileDoesNotExist($token->path());
        $this->assertFileDoesNotExist($this->sandbox.'/storage/app/update/state.json');
    }

    public function test_rollback_restores_previous_version_file(): void
    {
        $updateRoot = $this->sandbox.'/storage/app/update';
        mkdir($updateRoot.'/previous', 0777, true);
        file_put_contents($updateRoot.'/previous/VERSION', "0.6.3\n");
        file_put_contents($this->sandbox.'/VERSION', "0.9.0\n");

        $actions = new RecoveryActions($this->sandbox, $updateRoot, $this->sandbox.'/storage/app/backups');
        $result = $actions->rollback();

        $this->assertTrue($result['ok']);
        $this->assertSame('0.6.3', trim((string) file_get_contents($this->sandbox.'/VERSION')));
    }

    private function seedRecoverScript(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['EnvFile.php', 'RecoveryToken.php', 'RecoveryActions.php'] as $file) {
            copy($root.'/install/recovery/'.$file, $this->sandbox.'/install/recovery/'.$file);
        }
        copy($root.'/public/recover.php', $this->sandbox.'/public/recover.php');
    }

    /**
     * @param  array<string, string>  $params
     * @return array{code: int, body: string}
     */
    private function runRecover(array $params, string $method = 'GET'): array
    {
        $script = $this->sandbox.'/public/recover.php';
        $env = [
            'REQUEST_METHOD' => $method,
        ];

        $php = $this->phpBinary();
        $payload = base64_encode(json_encode([
            'get' => $method === 'GET' ? $params : (isset($params['token']) ? ['token' => $params['token']] : []),
            'post' => $method === 'POST' ? $params : [],
            'server' => $env,
            'script' => $script,
        ], JSON_THROW_ON_ERROR));

        $runner = '<?php
$data = json_decode(base64_decode($argv[1]), true);
$_GET = $data["get"];
$_POST = $data["post"];
$_SERVER = array_merge($_SERVER, $data["server"]);
ob_start();
try {
    include $data["script"];
} catch (Throwable $e) {
    http_response_code(500);
    echo $e->getMessage();
}
$body = ob_get_clean();
$code = http_response_code() ?: 200;
fwrite(STDOUT, $code."\n".$body);
';

        $tmp = $this->sandbox.'/runner.php';
        file_put_contents($tmp, $runner);

        $cmd = escapeshellarg($php).' '.escapeshellarg($tmp).' '.escapeshellarg($payload);
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        $joined = implode("\n", $output);
        $nl = strpos($joined, "\n");
        if ($nl === false) {
            return ['code' => 500, 'body' => $joined];
        }

        return [
            'code' => (int) substr($joined, 0, $nl),
            'body' => substr($joined, $nl + 1),
        ];
    }

    private function phpBinary(): string
    {
        $herd = getenv('USERPROFILE');
        if (is_string($herd) && $herd !== '') {
            $php83 = $herd.'\\.config\\herd\\bin\\php83.bat';
            if (is_file($php83)) {
                return $php83;
            }
        }

        return PHP_BINARY;
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
