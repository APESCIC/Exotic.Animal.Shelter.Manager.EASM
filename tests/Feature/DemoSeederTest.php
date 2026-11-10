<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Person;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(0, Animal::query()->count());
    }

    public function test_it_creates_clearly_fake_records_locally(): void
    {
        $this->app->instance('env', 'local');

        $this->seed(DemoSeeder::class);

        $this->assertSame(40, Animal::query()->count());
        $this->assertSame(30, Person::query()->count());

        foreach (Person::all() as $person) {
            $this->assertStringEndsWith('@example.test', (string) $person->email);
            $this->assertMatchesRegularExpression('/^07700 900\d{3}$/', (string) $person->phone);
            $this->assertStringStartsWith('ZZ99', (string) $person->postcode);
        }
        $this->assertTrue(Animal::query()->where('identifying_code', 'DEMO-0001')->exists());
    }
}
