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
    if (! in_array(config('database.connections.'.config('database.default').'.host'), ['127.0.0.1', 'localhost', null], true)) {
        return 'Demo data only loads into a database on this computer (127.0.0.1). Nothing was changed.';
    }

    return null;
};

Artisan::command('demo:seed', function () use ($demoGuard) {
    if ($problem = $demoGuard()) {
        $this->error($problem);

        return 1;
    }

    $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    $this->info('Demo data loaded. Logins: docs/laragon/demo-data.md');

    return 0;
})->purpose('Load the fake demo data (Laragon only, APP_ENV=local)');

Artisan::command('demo:reset', function () use ($demoGuard) {
    if ($problem = $demoGuard()) {
        $this->error($problem);

        return 1;
    }

    $this->call('migrate:fresh', ['--force' => true]);
    $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
    $this->info('Local database emptied and demo data reloaded. Logins: docs/laragon/demo-data.md');

    return 0;
})->purpose('Empty the local database and reload the fake demo data (Laragon only, APP_ENV=local)');
