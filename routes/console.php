<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$demoGuard = function (): ?string {
    if (! app()->environment('local')) {
        return 'Demo data only loads with APP_ENV=local. Nothing was changed.';
    }

    foreach (['CLOUDRON_MYSQL_HOST', 'CLOUDRON_MYSQL_DATABASE', 'CLOUDRON_MYSQL_URL'] as $key) {
        if (filled(env($key))) {
            return 'Demo data refuses to run when Cloudron database variables are set. Nothing was changed.';
        }
    }

    $connection = (string) config('database.default');
    $config = config("database.connections.{$connection}", []);
    $driver = $config['driver'] ?? null;
    $host = $config['host'] ?? null;
    $database = (string) ($config['database'] ?? '');

    if ($driver !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost', null, ''], true)) {
        return 'Demo data only loads into a database on this computer (127.0.0.1). Nothing was changed.';
    }

    if ($driver !== 'sqlite' && $database !== '' && $database !== 'easm' && ! str_ends_with($database, '_local')) {
        return 'Demo data only loads into easm or a database name ending in _local. Nothing was changed.';
    }

    return null;
};

Artisan::command('demo:seed {--fresh : Empty the local database before seeding}', function () use ($demoGuard) {
    if ($problem = $demoGuard()) {
        $this->error($problem);

        return 1;
    }

    if ($this->option('fresh')) {
        $this->call('migrate:fresh', ['--force' => true]);
    }

    $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    $this->info($this->option('fresh')
        ? 'Local database emptied and demo data reloaded. Logins: docs/local/demo-data.md'
        : 'Demo data loaded. Logins: docs/local/demo-data.md');

    return 0;
})->purpose('Load the fake demo data (local Herd preview only, APP_ENV=local)');

Artisan::command('demo:reset', function () {
    return $this->call('demo:seed', ['--fresh' => true]);
})->purpose('Empty the local database and reload the fake demo data (alias of demo:seed --fresh)');
