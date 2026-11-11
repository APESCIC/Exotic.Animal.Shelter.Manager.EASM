<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_status_and_version(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', config('app.version'))
            ->assertJsonPath('app', config('app.name'))
            ->assertJsonPath('checks.app', true)
            ->assertJsonPath('checks.database', true);
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
