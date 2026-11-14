<?php

namespace App\Http\Middleware;

use App\Install\InstallationState;
use App\Update\UpdateChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Poor-man's scheduler: when an admin loads a page, check for updates at most once a day.
 */
class CheckForUpdates
{
    public function __construct(
        private UpdateChecker $checker,
        private InstallationState $installation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCheck($request)) {
            try {
                $this->checker->maybeCheck();
            } catch (Throwable) {
                // Never break a page load over an update check.
            }
        }

        return $next($request);
    }

    private function shouldCheck(Request $request): bool
    {
        if (! $this->installation->isInstalled()) {
            return false;
        }

        if (! $this->checker->enabled()) {
            return false;
        }

        $user = $request->user();
        if ($user === null || ! $user->role->isAdmin()) {
            return false;
        }

        if ($request->is('cron/*') || $request->is('health') || $request->is('up')) {
            return false;
        }

        return true;
    }
}
