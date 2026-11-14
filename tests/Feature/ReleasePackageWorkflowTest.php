<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleasePackageWorkflowTest extends TestCase
{
    public function test_release_package_workflow_runs_only_on_published_releases(): void
    {
        $path = base_path('.github/workflows/release-package.yml');
        $this->assertFileExists($path);

        $yaml = (string) file_get_contents($path);

        $this->assertStringContainsString("release:\n    types: [published]", $yaml);
        $this->assertStringNotContainsString('push:', $yaml);
        $this->assertStringNotContainsString('pull_request:', $yaml);
        $this->assertStringContainsString('MINISIGN_SECRET_KEY', $yaml);
        $this->assertStringContainsString('scripts/build-release-package.sh', $yaml);
    }

    public function test_minisign_public_key_ships_in_the_app(): void
    {
        $path = base_path('resources/keys/easm-release.minisign.pub');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $lines = preg_split('/\R/', trim($contents)) ?: [];

        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('minisign public key', $lines[0]);
        $this->assertMatchesRegularExpression('/^RW[A-Za-z0-9+\/]{50,}$/', $lines[1]);
        $this->assertStringContainsString('0263FE993EE2A87C', $contents);
    }

    public function test_build_release_package_script_excludes_env_and_includes_assets(): void
    {
        $path = base_path('scripts/build-release-package.sh');
        $this->assertFileExists($path);

        $script = (string) file_get_contents($path);

        $this->assertStringContainsString("--exclude='/.env'", $script);
        $this->assertStringContainsString('vendor/', $script);
        $this->assertStringContainsString('public/css/easm.css', $script);
        $this->assertStringContainsString('public/js/easm.js', $script);
    }
}
