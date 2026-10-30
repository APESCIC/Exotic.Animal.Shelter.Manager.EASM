<?php

namespace App\Http\Middleware;

use App\Support\DevLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginDevUser
{
    public function __construct(private DevLogin $devLogin) {}

    /**
     * Sign guests in as the default QA account while local auto-login is on.
     *
     * A deliberate sign-out pauses auto-login for the session so the login
     * page stays reachable; the role switcher resumes it.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->devLogin->enabled()) {
            return $next($request);
        }

        if ($request->routeIs('logout')) {
            $response = $next($request);
            $request->session()->put(DevLogin::PAUSED_SESSION_KEY, true);

            return $response;
        }

        if (Auth::guest()
            && ! $request->session()->get(DevLogin::PAUSED_SESSION_KEY, false)
            && ! $request->routeIs('install.*', 'login', 'login.store', 'public.*', 'feeds.*', 'dev.*')) {
            Auth::login($this->devLogin->userFor($this->devLogin->defaultRole()));
        }

        return $next($request);
    }
}
