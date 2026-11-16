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

    private function enableQaGate(string $environment = 'local', bool $autoLogin = true): void
    {
        $this->app['env'] = $environment;
        config([
            'easm.dev_login.enabled' => true,
            'easm.dev_login.auto_login' => $autoLogin,
            'easm.dev_login.role' => 'admin',
        ]);
    }

    private function postQaLogin(string $role, ?string $returnTo = null)
    {
        $payload = ['role' => $role];
        if ($returnTo !== null) {
            $payload['return_to'] = $returnTo;
        }

        return $this->post(route('qa.login'), $payload);
    }

    public function test_guest_is_auto_logged_in_as_default_role_in_local(): void
    {
        $this->enableQaGate();
        config(['easm.dev_login.role' => 'staff']);

        $this->get('/')
            ->assertOk()
            ->assertSee('QA Staff', false)
            ->assertSee('Local QA — signed in as', false)
            ->assertSee('Developer QA', false);

        $user = User::query()->where('email', 'staff@easm.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Staff, $user->role);
    }

    public function test_unknown_default_role_falls_back_to_admin(): void
    {
        $this->enableQaGate();
        config(['easm.dev_login.role' => 'superuser']);

        $this->get('/')->assertOk();

        $this->assertSame('admin@easm.test', auth()->user()?->email);
    }

    public function test_qa_gate_off_hides_panel_and_routes(): void
    {
        $this->app['env'] = 'local';
        config([
            'easm.dev_login.enabled' => false,
            'easm.dev_login.auto_login' => true,
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('qa.index'))->assertNotFound();
        $this->post(route('qa.login'), ['role' => 'admin'])->assertNotFound();
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
        $this->get(route('login'))->assertOk()
            ->assertDontSee('Developer sign-in', false)
            ->assertDontSee('Local only', false);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_qa_gate_on_without_auto_login_shows_panel_and_switcher(): void
    {
        $this->enableQaGate(autoLogin: false);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Developer sign-in', false)
            ->assertSee('Local only', false)
            ->assertSee('admin@easm.test', false);

        $this->postQaLogin('admin')
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Signed in as '.DevLogin::name(UserRole::Admin).'.');

        $this->assertSame('admin@easm.test', auth()->user()?->email);
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Local QA — signed in as', false)
            ->assertSee('Admin', false);
    }

    public function test_qa_fallback_page_is_available_when_gate_is_on(): void
    {
        $this->enableQaGate(autoLogin: false);

        $this->get(route('qa.index'))
            ->assertOk()
            ->assertSee('Developer QA', false)
            ->assertSee('Developer sign-in', false);
    }

    public function test_production_ignores_the_flags(): void
    {
        $this->enableQaGate('production');

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('qa.index'))->assertNotFound();
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('Developer sign-in', false);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_staging_ignores_the_flags(): void
    {
        $this->enableQaGate('staging');

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('qa.index'))->assertNotFound();
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
    }

    public function test_testing_environment_ignores_the_flags(): void
    {
        config([
            'easm.dev_login.enabled' => true,
            'easm.dev_login.auto_login' => true,
        ]);

        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('qa.index'))->assertNotFound();
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
    }

    public function test_dev_auto_login_alone_is_not_enough(): void
    {
        $this->app['env'] = 'local';
        config([
            'easm.dev_login.enabled' => false,
            'easm.dev_login.auto_login' => true,
        ]);

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dev.login-as', 'admin'))->assertNotFound();
    }

    public function test_role_switcher_logs_in_with_each_role(): void
    {
        $this->enableQaGate(autoLogin: false);
        $this->withoutMiddleware(PreventRequestForgery::class);

        foreach (UserRole::cases() as $role) {
            $this->postQaLogin($role->value)
                ->assertRedirect(route('home'))
                ->assertSessionHas('status', 'Signed in as '.DevLogin::name($role).'.');

            $user = auth()->user();
            $this->assertNotNull($user);
            $this->assertSame(DevLogin::email($role), $user->email);
            $this->assertSame($role, $user->role);
        }

        $this->assertSame(count(UserRole::cases()), User::query()->count());
    }

    public function test_legacy_get_switcher_still_works_behind_the_gate(): void
    {
        $this->enableQaGate(autoLogin: false);

        $this->get(route('dev.login-as', 'volunteer'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Signed in as '.DevLogin::name(UserRole::Volunteer).'.');

        $this->assertSame('volunteer@easm.test', auth()->user()?->email);
    }

    public function test_role_switcher_rejects_unknown_roles(): void
    {
        $this->enableQaGate(autoLogin: false);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('qa.login'), ['role' => 'superuser'])->assertSessionHasErrors('role');
        $this->get('/dev/login-as/superuser')->assertNotFound();
    }

    public function test_sign_out_pauses_auto_login_until_a_role_is_picked(): void
    {
        $this->enableQaGate();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Developer sign-in', false)
            ->assertSee('volunteer@easm.test', false);
        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->postQaLogin('volunteer')->assertRedirect(route('home'));
        $this->assertSame('volunteer@easm.test', auth()->user()?->email);
    }

    public function test_qa_logout_pauses_auto_login(): void
    {
        $this->enableQaGate();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->get('/')->assertOk();
        $this->post(route('qa.logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Signed out of QA.');

        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_forbidden_return_path_falls_back_to_dashboard_after_switch(): void
    {
        $this->enableQaGate(autoLogin: false);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->postQaLogin('readonly', '/admin')
            ->assertRedirect('/admin');

        $this->get('/admin')
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'That page is not available for this QA role. You are on the dashboard.');

        $this->assertSame('readonly@easm.test', auth()->user()?->email);
    }

    public function test_unsafe_return_paths_are_ignored(): void
    {
        $this->enableQaGate(autoLogin: false);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->postQaLogin('admin', 'https://evil.example/phish')
            ->assertRedirect(route('home'));

        $this->postQaLogin('staff', '//evil.example')
            ->assertRedirect(route('home'));
    }

    public function test_public_and_login_routes_stay_guest(): void
    {
        $this->enableQaGate();

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
