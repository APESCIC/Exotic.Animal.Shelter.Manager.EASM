<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\DevLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DevLoginController extends Controller
{
    public function show(DevLogin $devLogin): View
    {
        abort_unless($devLogin->enabled(), 404);

        return view('qa.index');
    }

    public function login(Request $request, DevLogin $devLogin): RedirectResponse
    {
        abort_unless($devLogin->enabled(), 404);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_column(UserRole::cases(), 'value'))],
            'return_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $role = UserRole::from($validated['role']);
        $user = $devLogin->userFor($role);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(DevLogin::PAUSED_SESSION_KEY);

        $returnTo = DevLogin::safeReturnPath($validated['return_to'] ?? null);
        if ($returnTo !== null) {
            $request->session()->flash(DevLogin::SWITCHED_SESSION_KEY, true);

            return redirect()->to($returnTo)
                ->with('status', "Signed in as {$user->name}.");
        }

        return redirect()->route('home')
            ->with('status', "Signed in as {$user->name}.");
    }

    public function logout(Request $request, DevLogin $devLogin): RedirectResponse
    {
        abort_unless($devLogin->enabled(), 404);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put(DevLogin::PAUSED_SESSION_KEY, true);

        return redirect()->route('login')
            ->with('status', 'Signed out of QA.');
    }

    /**
     * Legacy GET switcher: keep behind the gate, but prefer POST /qa/login.
     */
    public function __invoke(Request $request, UserRole $role, DevLogin $devLogin): RedirectResponse
    {
        abort_unless($devLogin->enabled(), 404);

        $request->merge(['role' => $role->value]);

        return $this->login($request, $devLogin);
    }
}
