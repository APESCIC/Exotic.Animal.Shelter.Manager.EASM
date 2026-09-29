<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureInstallerAccess;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Must run before auth so uninstalled hosts reach /install, not /login.
        $middleware->web(prepend: [
            EnsureInstallerAccess::class,
        ]);

        $middleware->api(prepend: [
            EnsureInstallerAccess::class,
        ]);

        $middleware->priority([
            EnsureInstallerAccess::class,
            Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
