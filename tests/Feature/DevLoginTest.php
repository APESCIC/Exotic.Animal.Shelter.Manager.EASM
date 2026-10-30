<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\DevLogin;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevUserSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DevLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableDevLogin(string $environment = 'local'): void
    {
        $this->app['env'] = $environment;
        config(['easm.dev_login.auto_login' => true, 'easm.dev_login.role' => 'admin']);
    }

    public function test_guest_is_auto_logged_in_as_default_role_in_local(): void
    {
        $this->enableDevLogin();
        config(['easm.dev_login.role' => 'staff']);

        $this->get('/')
            ->assertOk()
            ->assertSee('QA Staff', false)
            ->assertSee('Development: sign in as', false);

        $user = User::query()->where('email', 'staff@easm.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Staff, $user->role);
    }

    public function test_unknown_default_role_falls_back_to_admin(): void
    {
        $this->enableDevLogin();
        config(['easm.dev_login.role' => 'superuser']);

        $this->get('/')->assertOk();

        $this->assertSame('admin@easm.test', auth()->user()?->email);
    }

    public function test_nothing_happens_when_flag_is_off(): void
    {
        $this->app['env'] = 'local';
        config(['easm.dev_login.auto_login' => false]);

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_production_ignores_the_flag(): void
    {
        $this->enableDevLogin('production');

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Development: sign in as', false);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_testing_environment_ignores_the_flag(): void
    {
        config(['easm.dev_login.auto_login' => true]);

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
    }

    public function test_role_switcher_logs_in_with_each_role(): void
    {
        $this->enableDevLogin();

        foreach (UserRole::cases() as $role) {
            $this->get(route('dev.login-as', $role))
                ->assertRedirect(route('home'))
                ->assertSessionHas('status', 'Signed in as '.DevLogin::name($role).'.');

            $user = auth()->user();
            $this->assertNotNull($user);
            $this->assertSame(DevLogin::email($role), $user->email);
            $this->assertSame($role, $user->role);
        }

        $this->assertSame(count(UserRole::cases()), User::query()->count());
    }

    public function test_role_switcher_rejects_unknown_roles(): void
    {
        $this->enableDevLogin();

        $this->get('/dev/login-as/superuser')->assertNotFound();
    }

    public function test_sign_out_pauses_auto_login_until_a_role_is_picked(): void
    {
        $this->enableDevLogin();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Development: sign in as', false)
            ->assertSee('volunteer@easm.test', false);
        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('dev.login-as', 'volunteer'))->assertRedirect(route('home'));
        $this->assertSame('volunteer@easm.test', auth()->user()?->email);
    }

    public function test_public_and_login_routes_stay_guest(): void
    {
        $this->enableDevLogin();

        $this->get(route('login'))->assertOk();
        $this->get(route('public.apply.index'))->assertOk();
        $this->get(route('public.adopt.index'))->assertOk();

        $this->assertGuest();
    }

    public function test_dev_user_seeder_creates_one_login_per_role_idempotently(): void
    {
        $this->seed(DevUserSeeder::class);
        User::query()->where('email', 'staff@easm.test')->update(['role' => UserRole::Readonly->value]);
        $this->seed(DevUserSeeder::class);

        $this->assertSame(count(UserRole::cases()), User::query()->count());

        foreach (UserRole::cases() as $role) {
            $user = User::query()->where('email', DevLogin::email($role))->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check(DevLogin::PASSWORD, $user->password));
        }
    }

    public function test_dev_user_seeder_refuses_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        try {
            (new DevUserSeeder)->run();
        } finally {
            $this->assertSame(0, User::query()->count());
        }
    }

    public function test_database_seeder_adds_dev_users_only_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(UserRole::cases()), User::query()->count());
        $this->assertDatabaseHas('users', ['email' => 'admin@easm.test', 'role' => 'admin']);
    }
}
