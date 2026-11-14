<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_status_and_version(): void
    {
        $response = $this->getJson('/health');

        $fromFile = trim((string) file_get_contents(base_path('VERSION')));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', $fromFile)
            ->assertJsonPath('app', config('app.name'))
            ->assertJsonPath('checks.app', true)
            ->assertJsonPath('checks.database', true)
            ->assertJsonStructure(['schema']);
    }

    public function test_health_version_equals_the_version_file(): void
    {
        $fromFile = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertNotSame('', $fromFile);
        $this->assertSame($fromFile, $this->getJson('/health')->assertOk()->json('version'));
    }

    public function test_health_schema_changes_after_a_migration(): void
    {
        $before = $this->getJson('/health')->assertOk()->json('schema');
        $this->assertIsString($before);
        $this->assertNotSame('', $before);

        DB::table('migrations')->insert([
            'migration' => '9999_99_99_999999_health_schema_probe',
            'batch' => 9999,
        ]);

        $after = $this->getJson('/health')->assertOk()->json('schema');

        $this->assertSame('9999_99_99_999999_health_schema_probe', $after);
        $this->assertNotSame($before, $after);
    }

    public function test_health_adds_the_php_handler_and_revision_on_the_local_preview_only(): void
    {
        $this->assertArrayNotHasKey('local', $this->getJson('/health')->json());

        $this->app->instance('env', 'local');
        $local = $this->getJson('/health')->assertOk()->json('local');

        $this->assertSame(PHP_VERSION, $local['php']);
        $this->assertSame(PHP_SAPI, $local['php_sapi']);
        $this->assertArrayHasKey('git_revision', $local);
    }

    public function test_health_endpoint_does_not_expose_secrets(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();

        $payload = $response->json();
        $encoded = json_encode($payload);

        $this->assertIsArray($payload);
        $this->assertStringNotContainsString((string) config('app.key'), (string) $encoded);
        $this->assertArrayNotHasKey('env', $payload);
        $this->assertArrayNotHasKey('database', $payload);
    }
}
