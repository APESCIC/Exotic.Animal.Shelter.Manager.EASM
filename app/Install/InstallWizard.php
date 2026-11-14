<?php

namespace App\Install;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\SharedHosting;
use Easm\Requirements\HostEnvironment;
use Easm\Requirements\RequirementsChecker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

/**
 * Stepped browser installer: requirements → database → details → migrate → finish.
 */
class InstallWizard
{
    public const SESSION_DATABASE = 'install.database';

    public const SESSION_DETAILS = 'install.details';

    public function __construct(
        private InstallationState $state,
        private EnvFile $envFile,
        private DatabaseConnector $database,
        private ChunkedMigrator $migrator,
        private CloudronDatabase $cloudron = new CloudronDatabase,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function requirements(): array
    {
        $env = new HostEnvironment(base_path(), [
            'installed' => $this->state->isInstalled(),
        ]);

        return (new RequirementsChecker($env))->run();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string}
     */
    public function saveDatabase(array $input): array
    {
        $source = $this->databaseSource($input);

        if ($source !== DatabaseSource::Managed && ! app()->runningUnitTests()) {
            $credentials = $source === DatabaseSource::Cloudron
                ? $this->cloudron->credentials()
                : $this->credentials($input);
            $this->database->test($credentials);
            $this->database->apply($credentials);
        }

        Session::put(self::SESSION_DATABASE, [
            'source' => $source->value,
            'input' => $input,
        ]);

        return [
            'ok' => true,
            'message' => 'Database connection looks good.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: bool, message: string}
     */
    public function saveDetails(array $input): array
    {
        Session::put(self::SESSION_DETAILS, [
            'organisation' => (string) $input['organisation'],
            'timezone' => (string) $input['timezone'],
            'admin_name' => (string) $input['admin_name'],
            'admin_email' => (string) $input['admin_email'],
            'admin_password' => (string) $input['admin_password'],
        ]);

        return [
            'ok' => true,
            'message' => 'Organisation and admin details saved.',
        ];
    }

    /**
     * @return array{done: bool, pending: int, ran: int, output: string}
     */
    public function migrateStep(): array
    {
        $this->applyStoredDatabase();

        return $this->migrator->runStep();
    }

    /**
     * @return array{ok: bool, message: string, redirect: string}
     */
    public function finish(): array
    {
        $details = Session::get(self::SESSION_DETAILS);
        if (! is_array($details)) {
            throw new \RuntimeException('Organisation and admin details are missing. Go back a step.');
        }

        $this->applyStoredDatabase();

        if ($this->migrator->pendingCount() > 0) {
            throw new \RuntimeException('Migrations are not finished yet.');
        }

        User::query()->updateOrCreate(
            ['email' => $details['admin_email']],
            [
                'name' => $details['admin_name'],
                'password' => $details['admin_password'],
                'role' => UserRole::Admin,
            ],
        );

        $existingSettings = Setting::current();
        if ($existingSettings === null) {
            Setting::query()->create([
                'organisation_name' => $details['organisation'],
                'locale' => 'en_GB',
                'timezone' => $details['timezone'],
            ]);
        } else {
            $existingSettings->update([
                'organisation_name' => $details['organisation'],
                'locale' => 'en_GB',
                'timezone' => $details['timezone'],
            ]);
        }

        $database = Session::get(self::SESSION_DATABASE, []);
        $source = DatabaseSource::tryFrom((string) ($database['source'] ?? '')) ?? DatabaseSource::Custom;
        $input = is_array($database['input'] ?? null) ? $database['input'] : [];
        $input = array_merge($input, $details);

        if ($source === DatabaseSource::Cloudron) {
            $this->envFile->remove($this->state->envPath(), ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD']);
        }

        $this->envFile->write($this->state->envPath(), $this->envValues($input, $source));

        SharedHosting::ensureAppTempDirectory();
        SharedHosting::ensurePublicStorage();

        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        config([
            'app.name' => $details['organisation'],
            'app.timezone' => $details['timezone'],
            'easm.installed' => true,
        ]);

        $this->state->markInstalled();
        Session::forget([self::SESSION_DATABASE, self::SESSION_DETAILS]);

        return [
            'ok' => true,
            'message' => 'Installation complete.',
            'redirect' => url('/'),
        ];
    }

    /**
     * One-shot install used by the classic form POST and older tests.
     *
     * @param  array<string, mixed>  $input
     */
    public function installAll(array $input): void
    {
        $this->saveDatabase($input);
        $this->saveDetails($input);

        do {
            $step = $this->migrateStep();
        } while (! $step['done']);

        $this->finish();
    }

    private function applyStoredDatabase(): void
    {
        $database = Session::get(self::SESSION_DATABASE);
        if (! is_array($database)) {
            throw new \RuntimeException('Database settings are missing. Go back a step.');
        }

        $source = DatabaseSource::tryFrom((string) ($database['source'] ?? '')) ?? DatabaseSource::Custom;
        $input = is_array($database['input'] ?? null) ? $database['input'] : [];

        if ($source === DatabaseSource::Managed || app()->runningUnitTests()) {
            return;
        }

        $credentials = $source === DatabaseSource::Cloudron
            ? $this->cloudron->credentials()
            : $this->credentials($input);
        $this->database->apply($credentials);
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
