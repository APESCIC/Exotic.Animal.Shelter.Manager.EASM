<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\Animal;
use App\Models\CitesCertificate;
use App\Models\DiaryTask;
use App\Models\Diet;
use App\Models\DwaLicence;
use App\Models\Enclosure;
use App\Models\EnclosureLog;
use App\Models\MedicalRecord;
use App\Models\Movement;
use App\Models\Person;
use App\Models\StockItem;
use App\Models\User;
use App\Support\DevLogin;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_outside_the_local_environment(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }

    public function test_the_demo_commands_refuse_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('demo:seed')->expectsOutputToContain('APP_ENV=local')->assertExitCode(1);
        $this->artisan('demo:reset')->expectsOutputToContain('APP_ENV=local')->assertExitCode(1);
        $this->artisan('demo:seed', ['--fresh' => true])->expectsOutputToContain('APP_ENV=local')->assertExitCode(1);
        $this->assertSame(0, Animal::query()->count());
    }

    public function test_the_seeder_refuses_when_cloudron_variables_are_set(): void
    {
        $this->app->instance('env', 'local');
        $this->app->detectEnvironment(fn (): string => 'local');

        putenv('CLOUDRON_MYSQL_HOST=mysql.cloudron');
        $_ENV['CLOUDRON_MYSQL_HOST'] = 'mysql.cloudron';
        $_SERVER['CLOUDRON_MYSQL_HOST'] = 'mysql.cloudron';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Cloudron');
            (new DemoSeeder)->run();
        } finally {
            putenv('CLOUDRON_MYSQL_HOST');
            unset($_ENV['CLOUDRON_MYSQL_HOST'], $_SERVER['CLOUDRON_MYSQL_HOST']);
        }
    }

    public function test_it_creates_clearly_fake_records_locally(): void
    {
        $this->app->instance('env', 'local');

        $this->seed(DemoSeeder::class);

        $this->assertSame(40, Animal::query()->count());
        $this->assertSame(30, Person::query()->count());
        $this->assertTrue(Enclosure::query()->where('zone', 'Quarantine')->exists());
        $this->assertTrue(Movement::query()->where('type', MovementType::Quarantine)->exists());
        $this->assertTrue(CitesCertificate::query()->where('certificate_number', 'GB-A10-DEMO-0001')->exists());
        $this->assertTrue(DwaLicence::query()->where('licence_number', 'DWA-DEMO-0001')->exists());
        $this->assertGreaterThan(0, Diet::query()->count());
        $this->assertGreaterThan(0, EnclosureLog::query()->count());
        $this->assertGreaterThan(0, MedicalRecord::query()->count());
        $this->assertGreaterThan(0, StockItem::query()->count());

        $species = Animal::query()->pluck('species')->implode(' ');
        $this->assertStringContainsString('snake', strtolower($species));
        $this->assertStringContainsString('marmoset', strtolower($species));
        $this->assertStringContainsString('parrot', strtolower($species));

        foreach (Person::all() as $person) {
            $this->assertStringEndsWith('@example.test', (string) $person->email);
            $this->assertMatchesRegularExpression('/^07700 900\d{3}$/', (string) $person->phone);
            $this->assertStringStartsWith('ZZ99', (string) $person->postcode);
        }
        $this->assertTrue(Animal::query()->where('identifying_code', 'DEMO-0001')->exists());

        foreach (UserRole::cases() as $role) {
            $this->assertDatabaseHas('users', ['email' => DevLogin::email($role)]);
        }

        $assigneeIds = DiaryTask::query()->pluck('assigned_to')->filter()->unique();
        $this->assertNotEmpty($assigneeIds);
        $qaIds = collect(UserRole::cases())
            ->map(fn (UserRole $role) => User::query()->where('email', DevLogin::email($role))->value('id'))
            ->filter();
        $this->assertNotEmpty($assigneeIds->intersect($qaIds));
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        $this->app->instance('env', 'local');

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(40, Animal::query()->count());
        $this->assertSame(30, Person::query()->count());
        $this->assertSame(10, Enclosure::query()->where('name', 'like', 'Demo ENC-%')->count());
        $this->assertSame(1, CitesCertificate::query()->where('certificate_number', 'GB-A10-DEMO-0001')->count());
        $this->assertSame(1, DwaLicence::query()->where('licence_number', 'DWA-DEMO-0001')->count());
        $this->assertSame(count(UserRole::cases()), User::query()->where('email', 'like', '%@easm.test')->count());
    }

    public function test_demo_seed_registers_fresh_and_reset_alias(): void
    {
        $seed = Artisan::all()['demo:seed'] ?? null;
        $reset = Artisan::all()['demo:reset'] ?? null;

        $this->assertNotNull($seed);
        $this->assertNotNull($reset);
        $this->assertTrue($seed->getDefinition()->hasOption('fresh'));

        $this->app->instance('env', 'local');
        $this->artisan('demo:seed')->assertExitCode(0);
        $this->assertSame(40, Animal::query()->count());
        $this->assertTrue(Movement::query()->where('type', MovementType::Quarantine)->exists());
    }

    public function test_installer_sources_do_not_enable_demo_seeding(): void
    {
        $wizard = (string) file_get_contents(base_path('app/Install/InstallWizard.php'));
        $controller = (string) file_get_contents(base_path('app/Http/Controllers/InstallerController.php'));

        $this->assertStringNotContainsString('DemoSeeder', $wizard);
        $this->assertStringNotContainsString('demo:seed', $wizard);
        $this->assertStringNotContainsString('demo:reset', $wizard);
        $this->assertStringNotContainsString('DemoSeeder', $controller);
        $this->assertStringNotContainsString('demo:seed', $controller);
    }
}
