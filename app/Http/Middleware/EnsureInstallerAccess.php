<?php

namespace App\Http\Middleware;

use App\Install\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallerAccess
{
    public function __construct(private InstallationState $installation) {}

    /**
     * Send uninstalled requests to the wizard, and block the wizard once installed.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = $this->installation->isInstalled();
        $isInstaller = $request->routeIs('install.*')
            || $request->is('install', 'install/*');

        if (! $installed && ! $isInstaller) {
            return redirect()->route('install.show');
        }

        if ($installed && $isInstaller) {
            if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
                abort(403, 'This shelter is already installed.');
            }

            // JSON clients (requirements step) still receive a locked report.
            if ($request->expectsJson()) {
                return $next($request);
            }

            return redirect('/');
        }

        return $next($request);
    }
}
