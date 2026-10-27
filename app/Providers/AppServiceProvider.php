<?php

namespace App\Providers;

use App\Install\InstallationState;
use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(InstallationState::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(InstallationState $installation): void
    {
        Paginator::useBootstrapFour();

        if (! $installation->isInstalled()) {
            // The wizard runs before MySQL tables exist. Do not use database
            // drivers for session, cache, or queue until install has finished.
            if (config('session.driver') === 'database') {
                config(['session.driver' => 'file']);
            }

            if (config('cache.default') === 'database') {
                config(['cache.default' => 'file']);
            }

            if (config('queue.default') === 'database') {
                config(['queue.default' => 'sync']);
            }

            return;
        }

        try {
            Setting::current()?->applyToConfig();
        } catch (Throwable) {
            // Database may be unavailable during early boot or health checks.
        }
    }
}
