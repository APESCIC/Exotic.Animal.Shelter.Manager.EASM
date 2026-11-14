<?php

namespace Tests\Feature;

use App\Models\User;
use App\Update\UpdateStateMachine;
use App\Update\UpdateStatePresenter;
use App\Update\WebUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UpdateProgressUiTest extends TestCase
{
    use RefreshDatabase;

    private string $workRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workRoot = storage_path('framework/testing/update-ui-'.uniqid('', true));
        File::ensureDirectoryExists($this->workRoot);
        config(['easm.updates.state_path' => $this->workRoot]);
        $this->app->forgetInstance(UpdateStateMachine::class);
        $this->app->forgetInstance(WebUpdater::class);
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
        if (is_dir($this->workRoot)) {
            File::deleteDirectory($this->workRoot);
        }
        parent::tearDown();
    }

    public function test_presented_logs_never_contain_db_password_or_app_key(): void
    {
        config(['app.key' => 'base64:TEST-APP-KEY-VALUE-FOR-REDACTION']);
        putenv('DB_PASSWORD=super-secret-db-password');
        $_ENV['DB_PASSWORD'] = 'super-secret-db-password';
        $_SERVER['DB_PASSWORD'] = 'super-secret-db-password';

        $raw = [
            'status' => 'failed',
            'error' => 'Could not connect with password super-secret-db-password and key base64:TEST-APP-KEY-VALUE-FOR-REDACTION',
            'context' => [
                'APP_KEY' => 'base64:TEST-APP-KEY-VALUE-FOR-REDACTION',
                'DB_PASSWORD' => 'super-secret-db-password',
                'password' => 'super-secret-db-password',
                'note' => 'env APP_KEY=base64:TEST-APP-KEY-VALUE-FOR-REDACTION',
                'maintenance_secret' => 'should-also-hide',
                'recovery_token' => 'tok',
            ],
            'steps' => [],
        ];

        $presented = (new UpdateStatePresenter)->present($raw);
        $encoded = json_encode($presented, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('super-secret-db-password', $encoded);
        $this->assertStringNotContainsString('base64:TEST-APP-KEY-VALUE-FOR-REDACTION', $encoded);
        $this->assertStringNotContainsString('TEST-APP-KEY-VALUE-FOR-REDACTION', $encoded);
        $this->assertSame('[redacted]', $presented['context']['APP_KEY']);
        $this->assertSame('[redacted]', $presented['context']['DB_PASSWORD']);
        $this->assertSame('[redacted]', $presented['context']['maintenance_secret']);
        $this->assertNotEmpty($presented['steps_ui']);
    }

    public function test_public_gets_503_while_admin_reaches_progress_with_bypass_cookie(): void
    {
        $secret = 'test-maint-bypass-'.bin2hex(random_bytes(4));
        Artisan::call('down', [
            '--retry' => 60,
            '--secret' => $secret,
        ]);
        $this->assertTrue(app()->isDownForMaintenance());

        $this->get('/')->assertStatus(503);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $bypass = $this->get('/'.$secret);
        $bypass->assertRedirect();
        $cookie = collect($bypass->headers->getCookies())
            ->first(fn ($item) => $item->getName() === 'laravel_maintenance');
        $this->assertNotNull($cookie, 'Maintenance bypass cookie was not set.');

        $this->withUnencryptedCookie('laravel_maintenance', $cookie->getValue())
            ->get(route('admin.update.index'))
            ->assertOk()
            ->assertSee('Progress', false)
            ->assertSee('update-steps', false);
    }

    public function test_status_json_is_redacted_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $machine = new UpdateStateMachine($this->workRoot, 120);
        $machine->start('0.9.0', [
            'APP_KEY' => (string) config('app.key'),
            'DB_PASSWORD' => 'should-not-leak',
            'password' => 'should-not-leak',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.update.status.json'));
        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('should-not-leak', $body);
        if ((string) config('app.key') !== '') {
            $this->assertStringNotContainsString((string) config('app.key'), $body);
        }
        $response->assertJsonPath('state.context.password', '[redacted]');
    }
}
