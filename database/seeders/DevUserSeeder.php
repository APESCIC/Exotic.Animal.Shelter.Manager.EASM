<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DevLogin;
use Illuminate\Database\Seeder;
use RuntimeException;

class DevUserSeeder extends Seeder
{
    /**
     * Create one known-password QA login per role for local development.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DevUserSeeder only runs in local or testing environments.');
        }

        foreach (UserRole::cases() as $role) {
            User::query()->updateOrCreate(
                ['email' => DevLogin::email($role)],
                [
                    'name' => DevLogin::name($role),
                    'role' => $role,
                    'password' => DevLogin::PASSWORD,
                ],
            );
        }
    }
}
