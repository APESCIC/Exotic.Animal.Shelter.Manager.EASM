<?php

namespace App\Install;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\SharedHosting;
use Illuminate\Support\Facades\Artisan;

class Installer
{
    private const DATABASE_KEYS = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    public function __construct(
        private InstallationState $state,
        private EnvFile $envFile,
        private DatabaseConnector $database,
        private CloudronDatabase $cloudron = new CloudronDatabase,
    ) {}

    /**
     * Persist hosting config, migrate, create the first admin, and lock the wizard.
     *
     * @param  array<string, mixed>  $input
     */
    public function install(array $input): void
    {
        $source = $this->databaseSource($input);

        if ($source !== DatabaseSource::Managed && ! app()->runningUnitTests()) {
            $credentials = $source === DatabaseSource::Cloudron
                ? $this->cloudron->credentials()
                : $this->credentials($input);
            $this->database->test($credentials);
            $this->database->apply($credentials);
        }

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(Artisan::output()) ?: 'Database migrations failed.');
        }

        User::query()->updateOrCreate(
            ['email' => $input['admin_email']],
            [
                'name' => $input['admin_name'],
                'password' => $input['admin_password'],
                'role' => UserRole::Admin,
            ],
        );

        $existingSettings = Setting::current();

        if ($existingSettings === null) {
            Setting::query()->create([
                'organisation_name' => (string) $input['organisation'],
                'locale' => 'en_GB',
                'timezone' => (string) $input['timezone'],
            ]);
        } else {
            $existingSettings->update([
                'organisation_name' => (string) $input['organisation'],
                'locale' => 'en_GB',
                'timezone' => (string) $input['timezone'],
            ]);
        }

        // Write .env last. php artisan serve restarts when .env changes; doing
        // migrate + admin first avoids APP_INSTALLED=true with no admin user.
        if ($source === DatabaseSource::Cloudron) {
            $this->envFile->remove($this->state->envPath(), self::DATABASE_KEYS);
        }

        $this->envFile->write($this->state->envPath(), $this->envValues($input, $source));

        SharedHosting::ensureAppTempDirectory();
        SharedHosting::ensurePublicStorage();

        // A cached config (Cloudron's run.sh builds one) would hide the new .env values.
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        config([
            'app.name' => $input['organisation'],
            'app.timezone' => $input['timezone'],
            'easm.installed' => true,
        ]);

        $this->state->markInstalled();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    private function credentials(array $input): array
    {
        return [
            'host' => (string) $input['db_host'],
            'port' => (string) $input['db_port'],
            'database' => (string) $input['db_database'],
            'username' => (string) $input['db_username'],
            'password' => (string) ($input['db_password'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function envValues(array $input, DatabaseSource $source): array
    {
        $values = [
            'APP_NAME' => (string) $input['organisation'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => (string) $input['timezone'],
            'APP_INSTALLED' => 'true',
        ];

        $values += match ($source) {
            DatabaseSource::Managed => [],
            DatabaseSource::Cloudron => ['DB_CONNECTION' => 'mysql'],
            DatabaseSource::Custom => [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => (string) $input['db_host'],
                'DB_PORT' => (string) $input['db_port'],
                'DB_DATABASE' => (string) $input['db_database'],
                'DB_USERNAME' => (string) $input['db_username'],
                'DB_PASSWORD' => (string) ($input['db_password'] ?? ''),
            ],
        };

        if (blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $values['APP_KEY'] = $key;
            config(['app.key' => $key]);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function databaseSource(array $input): DatabaseSource
    {
        if (config('easm.managed_database')) {
            return DatabaseSource::Managed;
        }

        if ($this->cloudron->available() && ($input['db_source'] ?? null) === DatabaseSource::Cloudron->value) {
            return DatabaseSource::Cloudron;
        }

        return DatabaseSource::Custom;
    }
}
