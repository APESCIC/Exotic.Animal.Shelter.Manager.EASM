<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

class DevLogin
{
    public const PASSWORD = 'password';

    public const PAUSED_SESSION_KEY = 'dev_login.paused';

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
