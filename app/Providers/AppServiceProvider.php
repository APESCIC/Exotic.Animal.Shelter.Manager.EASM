<?php

namespace App\Providers;

use App\Backup\BackupService;
use App\Install\InstallationState;
use App\Models\Setting;
use App\Support\SharedHosting;
use App\Update\UpdateChecker;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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
        $this->app->singleton(BackupService::class, function () {
            return new BackupService(
                root: (string) config('easm.backups.path', storage_path('app/backups')),
            );
        });
        SharedHosting::ensureAppTempDirectory();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(InstallationState $installation): void
    {
        Paginator::useBootstrapFour();

        View::composer('layouts.app', function ($view): void {
            $notice = null;
            $user = auth()->user();
            if ($user?->role->isAdmin()) {
                try {
                    $notice = app(UpdateChecker::class)->status();
                } catch (Throwable) {
                    $notice = null;
                }
            }
            $view->with('updateNotice', $notice);
        });

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
