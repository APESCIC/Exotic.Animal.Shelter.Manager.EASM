<?php

namespace Tests\Feature;

use App\Install\InstallationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationMarkerTest extends TestCase
{
    use RefreshDatabase;

    private string $installedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installedPath = storage_path('framework/testing/installed-marker-'.uniqid('', true));
        config(['easm.installed_path' => $this->installedPath]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->installedPath)) {
            unlink($this->installedPath);
        }

        parent::tearDown();
    }

    public function test_mark_installed_records_installed_and_updated_versions(): void
    {
        config(['app.version' => '0.9.0']);

        $state = app(InstallationState::class);
        $state->markInstalled();

        $marker = $state->readMarker();

        $this->assertSame('0.9.0', $marker['installed_version']);
        $this->assertSame('0.9.0', $marker['updated_version']);
        $this->assertSame('0.9.0', $marker['version']);
        $this->assertArrayHasKey('installed_at', $marker);
        $this->assertArrayHasKey('updated_at', $marker);
    }

    public function test_mark_updated_keeps_installed_version_and_moves_updated_version(): void
    {
        config(['app.version' => '0.9.0']);
        $state = app(InstallationState::class);
        $state->markInstalled();

        $state->markUpdated('0.9.1');
        $marker = $state->readMarker();

        $this->assertSame('0.9.0', $marker['installed_version']);
        $this->assertSame('0.9.1', $marker['updated_version']);
        $this->assertSame('0.9.1', $marker['version']);
    }
}
