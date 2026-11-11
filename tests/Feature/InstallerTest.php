<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Install\DatabaseConnector;
use App\Install\EnvFile;
use App\Install\InstallationState;
use App\Install\Installer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $envPath;

    private string $installedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = storage_path('framework/testing/install-'.uniqid('', true).'.env');
        $this->installedPath = storage_path('framework/testing/installed-'.uniqid('', true));

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

    public function test_uninstalled_app_shows_the_installer(): void
    {
        $this->get('/')
            ->assertRedirect(route('install.show'));

        $this->get(route('install.show'))
            ->assertOk()
            ->assertSee('Install', false)
            ->assertSee('Organisation', false)
            ->assertSee('Europe/London', false)
            ->assertSee('name="db_database"', false)
            ->assertSee('name="admin_email"', false);
    }

    public function test_successful_install_writes_config_and_blocks_a_second_run(): void
    {
        $payload = $this->validPayload();

        $this->post(route('install.store'), $payload)
            ->assertRedirect('/');

        $this->assertFileExists($this->installedPath);

        $env = file_get_contents($this->envPath);
        $this->assertIsString($env);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_PORT=3306', $env);
        $this->assertStringContainsString('DB_DATABASE=easm_shelter', $env);
        $this->assertStringContainsString('DB_USERNAME=easm_user', $env);
        $this->assertStringContainsString('DB_PASSWORD=easm_secret', $env);
        $this->assertStringContainsString('APP_NAME="APES CIC Rescue"', $env);
        $this->assertStringContainsString('APP_TIMEZONE=Europe/London', $env);
        $this->assertStringContainsString('APP_INSTALLED=true', $env);

        $admin = User::query()->where('email', 'admin@example.org')->first();
        $this->assertNotNull($admin);
        $this->assertSame('Shelter Admin', $admin->name);
        $this->assertTrue(Hash::check('a-secure-password', $admin->password));
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(1, User::query()->count());

        $settings = Setting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame('APES CIC Rescue', $settings->organisation_name);
        $this->assertSame('en_GB', $settings->locale);
        $this->assertSame('Europe/London', $settings->timezone);

        $this->get(route('install.show'))->assertRedirect('/');

        $this->post(route('install.store'), array_merge($payload, [
            'organisation' => 'Second Shelter',
            'admin_email' => 'other@example.org',
        ]))->assertForbidden();

        $this->assertSame(1, User::query()->count());
        $this->assertStringContainsString('APP_NAME="APES CIC Rescue"', (string) file_get_contents($this->envPath));
        $this->assertStringNotContainsString('Second Shelter', (string) file_get_contents($this->envPath));
    }

    public function test_admin_user_exists_before_env_is_marked_installed(): void
    {
        $spy = new class extends EnvFile
        {
            public int $usersWhenInstalledWritten = -1;

            public function write(string $path, array $values): void
            {
                if (($values['APP_INSTALLED'] ?? null) === 'true') {
                    $this->usersWhenInstalledWritten = User::query()->count();
                }

                parent::write($path, $values);
            }
        };

        $installer = new Installer(
            $this->app->make(InstallationState::class),
            $spy,
            $this->app->make(DatabaseConnector::class),
        );

        $installer->install($this->validPayload());

        $this->assertGreaterThan(0, $spy->usersWhenInstalledWritten);
    }

    public function test_managed_database_install_skips_database_settings(): void
    {
        config(['easm.managed_database' => true]);

        $this->get(route('install.show'))
            ->assertOk()
            ->assertDontSee('name="db_database"', false)
            ->assertSee('name="admin_email"', false);

        $payload = array_diff_key($this->validPayload(), array_flip([
            'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        ]));

        $this->post(route('install.store'), $payload)
            ->assertRedirect('/');

        $this->assertFileExists($this->installedPath);

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('APP_INSTALLED=true', $env);
        $this->assertStringContainsString('APP_NAME="APES CIC Rescue"', $env);
        $this->assertStringNotContainsString('easm_shelter', $env);
        $this->assertStringNotContainsString('easm_user', $env);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
    }

    public function test_cloudron_database_choice_is_offered_only_on_cloudron(): void
    {
        $this->get(route('install.show'))
            ->assertOk()
            ->assertDontSee('name="db_source"', false)
            ->assertSee('name="db_database"', false);

        $this->useCloudronDatabase();

        $this->get(route('install.show'))
            ->assertOk()
            ->assertSee('Use the database Cloudron provides')
            ->assertSee('Enter my own database details')
            ->assertSee('value="cloudron" checked', false)
            ->assertSee('name="db_database"', false)
            ->assertDontSee('cloudron_user')
            ->assertDontSee('cloudron_db');
    }

    public function test_cloudron_database_install_keeps_credentials_out_of_env(): void
    {
        $this->useCloudronDatabase();

        $this->post(route('install.store'), $this->payloadWithoutDatabase() + ['db_source' => 'cloudron'])
            ->assertRedirect('/');

        $this->assertFileExists($this->installedPath);

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('APP_INSTALLED=true', $env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        foreach (['DB_HOST=', 'DB_PORT=', 'DB_DATABASE=', 'DB_USERNAME=', 'DB_PASSWORD='] as $key) {
            $this->assertStringNotContainsString(PHP_EOL.$key, $env);
        }
        $this->assertStringNotContainsString('cloudron_secret', $env);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
    }

    public function test_custom_database_on_cloudron_writes_its_credentials(): void
    {
        $this->useCloudronDatabase();

        $this->post(route('install.store'), $this->validPayload() + ['db_source' => 'custom'])
            ->assertRedirect('/');

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $env);
        $this->assertStringContainsString('DB_DATABASE=easm_shelter', $env);
        $this->assertStringContainsString('DB_USERNAME=easm_user', $env);
        $this->assertStringContainsString('DB_PASSWORD=easm_secret', $env);
        $this->assertStringNotContainsString('cloudron_secret', $env);
    }

    public function test_cloudron_install_needs_a_choice_and_custom_needs_details(): void
    {
        $this->useCloudronDatabase();

        $this->from(route('install.show'))
            ->post(route('install.store'), $this->payloadWithoutDatabase())
            ->assertSessionHasErrors('db_source');

        $this->from(route('install.show'))
            ->post(route('install.store'), $this->payloadWithoutDatabase() + ['db_source' => 'custom'])
            ->assertSessionHasErrors(['db_host', 'db_database', 'db_username']);

        $this->assertFileDoesNotExist($this->installedPath);
    }

    public function test_install_clears_a_cached_config_so_new_env_values_apply(): void
    {
        $cached = $this->app->getCachedConfigPath();
        $this->assertFileDoesNotExist($cached);

        file_put_contents($cached, '<?php return [];'.PHP_EOL);
        $this->app->instance('config_loaded_from_cache', true);

        try {
            $this->post(route('install.store'), $this->validPayload())
                ->assertSessionHasNoErrors()
                ->assertRedirect('/');

            $this->assertFileDoesNotExist($cached);
        } finally {
            if (is_file($cached)) {
                unlink($cached);
            }
        }
    }

    public function test_health_endpoint_still_works_after_install(): void
    {
        $this->post(route('install.store'), $this->validPayload())
            ->assertRedirect('/');

        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.app', true)
            ->assertJsonPath('checks.database', true);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'easm_shelter',
            'db_username' => 'easm_user',
            'db_password' => 'easm_secret',
            'organisation' => 'APES CIC Rescue',
            'timezone' => 'Europe/London',
            'admin_name' => 'Shelter Admin',
            'admin_email' => 'admin@example.org',
            'admin_password' => 'a-secure-password',
            'admin_password_confirmation' => 'a-secure-password',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function payloadWithoutDatabase(): array
    {
        return array_diff_key($this->validPayload(), array_flip([
            'db_host', 'db_port', 'db_database', 'db_username', 'db_password',
        ]));
    }

    private function useCloudronDatabase(): void
    {
        config(['easm.cloudron_database' => [
            'host' => 'mysql.cloudron',
            'port' => '3306',
            'database' => 'cloudron_db',
            'username' => 'cloudron_user',
            'password' => 'cloudron_secret',
        ]]);
    }
}
