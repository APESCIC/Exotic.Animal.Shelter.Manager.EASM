<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DevLogin
{
    public const PASSWORD = 'password';

    public const PAUSED_SESSION_KEY = 'dev_login.paused';

    /** Flashed for the request straight after a role switch. */
    public const SWITCHED_SESSION_KEY = 'dev_login.switched';

    /**
     * Whether the Developer QA login panel and role switcher are available.
     * Local Herd only, behind QA_LOGIN_ENABLED.
     */
    public function enabled(): bool
    {
        return app()->environment('local')
            && filter_var(config('easm.dev_login.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether guests are signed in automatically as the default QA role.
     * Requires the QA gate; DEV_AUTO_LOGIN alone is never enough.
     */
    public function autoLoginEnabled(): bool
    {
        return $this->enabled()
            && filter_var(config('easm.dev_login.auto_login'), FILTER_VALIDATE_BOOLEAN);
    }

    public function defaultRole(): UserRole
    {
        return UserRole::tryFrom((string) config('easm.dev_login.role')) ?? UserRole::Admin;
    }

    public static function email(UserRole $role): string
    {
        return $role->value.'@easm.test';
    }

    public static function name(UserRole $role): string
    {
        return 'QA '.$role->label();
    }

    /**
     * Whether the signed-in user is one of the fixed QA accounts.
     */
    public function isQaUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (UserRole::cases() as $role) {
            if ($user->email === self::email($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A path on this site to return to, or null if it could lead elsewhere.
     */
    public static function safeReturnPath(mixed $path): ?string
    {
        if (! is_string($path) || $path === '' || strlen($path) > 2000) {
            return null;
        }

        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || preg_match('/[\\\\\s]/', $path) === 1) {
            return null;
        }

        return $path;
    }

    /**
     * After a role switch, send the person to the dashboard when the new role
     * cannot open the page they returned to (403 or 404).
     */
    public function fallbackAfterSwitch(Request $request, int $status): ?RedirectResponse
    {
        if (! in_array($status, [403, 404], true) || ! $request->isMethod('GET') || ! $request->hasSession() || ! $this->enabled()) {
            return null;
        }

        if ($request->session()->pull(self::SWITCHED_SESSION_KEY) !== true) {
            return null;
        }

        return redirect()->route('home')
            ->with('status', 'That page is not available for this QA role. You are on the dashboard.');
    }

    /**
     * The QA account for a role, created on first use.
     */
    public function userFor(UserRole $role): User
    {
        $user = User::query()->firstOrNew(['email' => self::email($role)]);
        $user->fill(['name' => self::name($role), 'role' => $role]);

        if (! $user->exists) {
            $user->password = self::PASSWORD;
        }

        $user->save();

        return $user;
    }
}
