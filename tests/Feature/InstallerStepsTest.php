<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallerStepsTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    private string $installedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = storage_path('framework/testing/install-steps-'.uniqid('', true).'.env');
        $this->installedPath = storage_path('framework/testing/installed-steps-'.uniqid('', true));
        copy(base_path('.env.example'), $this->envPath);

        config([
            'easm.installed' => false,
            'easm.env_path' => $this->envPath,
            'easm.installed_path' => $this->installedPath,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->envPath, $this->installedPath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_requirements_step_returns_a_report(): void
    {
        $this->getJson(route('install.requirements'))
            ->assertOk()
            ->assertJsonStructure(['ok', 'locked', 'checks']);
    }

    public function test_database_step_stores_connection_settings(): void
    {
        $this->postJson(route('install.database'), $this->databasePayload())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(session()->has('install.database'));
    }

    public function test_details_step_stores_organisation_and_admin(): void
    {
        $this->postJson(route('install.details'), $this->detailsPayload())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('APES CIC Rescue', session('install.details.organisation'));
        $this->assertSame('admin@example.org', session('install.details.admin_email'));
    }

    public function test_migrate_step_reports_progress(): void
    {
        $this->postJson(route('install.database'), $this->databasePayload())->assertOk();

        $response = $this->postJson(route('install.migrate'));
        $response->assertOk()->assertJsonStructure(['ok', 'done', 'pending', 'ran']);
        $this->assertTrue($response->json('ok'));
    }

    public function test_finish_step_locks_the_installer(): void
    {
        $this->withSession([])
            ->postJson(route('install.database'), $this->databasePayload())
            ->assertOk();
        $this->postJson(route('install.details'), $this->detailsPayload())->assertOk();

        do {
            $migrate = $this->postJson(route('install.migrate'));
            $migrate->assertOk();
        } while (! $migrate->json('done'));

        $this->postJson(route('install.finish'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertFileExists($this->installedPath);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('APP_INSTALLED=true', $env);

        config(['easm.installed' => true]);
        $this->getJson(route('install.requirements'))->assertOk()->assertJsonPath('locked', true);
        $this->post(route('install.store'), array_merge($this->databasePayload(), $this->detailsPayload()))
            ->assertForbidden();
    }

    public function test_stepped_install_under_shared_host_limits(): void
    {
        $previousTime = ini_get('max_execution_time');
        $previousMemory = ini_get('memory_limit');
        ini_set('max_execution_time', '30');
        ini_set('memory_limit', '128M');

        try {
            $this->assertSame('30', (string) ini_get('max_execution_time'));
            $this->assertSame('128M', (string) ini_get('memory_limit'));

            $this->postJson(route('install.database'), $this->databasePayload())->assertOk();
            $this->postJson(route('install.details'), $this->detailsPayload())->assertOk();

            $guard = 0;
            do {
                $migrate = $this->postJson(route('install.migrate'));
                $migrate->assertOk();
                $guard++;
                $this->assertLessThan(500, $guard);
            } while (! $migrate->json('done'));

            $this->postJson(route('install.finish'))->assertOk()->assertJsonPath('ok', true);
            $this->assertFileExists($this->installedPath);
        } finally {
            if ($previousTime !== false) {
                ini_set('max_execution_time', (string) $previousTime);
            }
            if ($previousMemory !== false) {
                ini_set('memory_limit', (string) $previousMemory);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function databasePayload(): array
    {
        return [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'easm_shelter',
            'db_username' => 'easm_user',
            'db_password' => 'easm_secret',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function detailsPayload(): array
    {
        return [
            'organisation' => 'APES CIC Rescue',
            'timezone' => 'Europe/London',
            'admin_name' => 'Shelter Admin',
            'admin_email' => 'admin@example.org',
            'admin_password' => 'a-secure-password',
            'admin_password_confirmation' => 'a-secure-password',
        ];
    }
}
