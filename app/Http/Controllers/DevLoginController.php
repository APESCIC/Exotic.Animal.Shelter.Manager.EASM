<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\DevLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevLoginController extends Controller
{
    public function __invoke(Request $request, UserRole $role, DevLogin $devLogin): RedirectResponse
    {
        abort_unless($devLogin->enabled(), 404);

        $user = $devLogin->userFor($role);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(DevLogin::PAUSED_SESSION_KEY);

        return redirect()->route('home')->with('status', "Signed in as {$user->name}.");
    }
}
