<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalProfileTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return json_decode((string) file_get_contents(base_path('docs/local/profile.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function herdEnv(): array
    {
        $values = [];
        foreach (file(base_path('.env.herd.example'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
                $values[$match[1]] = trim($match[2], '"');
            }
        }

        return $values;
    }

    public function test_the_herd_env_example_matches_the_profile(): void
    {
        $profile = $this->profile();
        $env = $this->herdEnv();

        $this->assertSame('local', $env['APP_ENV']);
        $this->assertSame($profile['appUrl'], $env['APP_URL']);
        $this->assertSame('https://'.$profile['hostname'], $env['APP_URL']);
        $this->assertSame($profile['database']['host'], $env['DB_HOST']);
        $this->assertSame((string) $profile['database']['port'], $env['DB_PORT']);
        $this->assertSame($profile['database']['name'], $env['DB_DATABASE']);
        $this->assertSame($profile['database']['username'], $env['DB_USERNAME']);
        $this->assertSame('', $env['DB_PASSWORD']);
        $this->assertStringEndsWith('_local', $env['DB_DATABASE']);
        $this->assertSame('smtp', $env['MAIL_MAILER']);
        $this->assertSame($profile['mail']['host'], $env['MAIL_HOST']);
        $this->assertSame((string) $profile['mail']['port'], $env['MAIL_PORT']);
    }

    public function test_the_shipped_env_example_stays_production_safe(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("APP_ENV=production\n", str_replace("\r\n", "\n", $example));
        $this->assertStringContainsString("APP_DEBUG=false\n", str_replace("\r\n", "\n", $example));
    }

    public function test_the_site_php_and_live_reload_port_match_the_profile(): void
    {
        $profile = $this->profile();
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertSame($profile['site'].'.test', $profile['hostname']);
        $this->assertSame('php'.str_replace('.', '', $profile['php']), $profile['herdPhp']);
        $this->assertSame('cgi-fcgi', $profile['phpSapi']);
        $this->assertStringStartsWith('https://', $profile['liveUrl']);
        $this->assertSame($profile['appUrl'].':'.$profile['devServerPort'], $profile['previewUrl']);
        $this->assertStringContainsString('--config bs-config.cjs --proxy '.$profile['appUrl'].' --port '.$profile['devServerPort'], $package);
        $this->assertStringContainsString($profile['hostname'].'.crt', (string) file_get_contents(base_path('bs-config.cjs')));
    }

    public function test_public_htaccess_is_safe_under_fastcgi(): void
    {
        $depth = 0;
        $outside = [];
        foreach (file(base_path('public/.htaccess'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*<IfModule\s+(php_module|mod_php\d*\.c)\s*>/i', $line) === 1) {
                $depth++;
            } elseif ($depth > 0 && preg_match('#^\s*</IfModule>#i', $line) === 1) {
                $depth--;
            } elseif ($depth === 0 && preg_match('/^\s*php_(value|flag|admin_value|admin_flag)\b/i', $line) === 1) {
                $outside[] = trim($line);
            }
        }

        $this->assertSame([], $outside);
        $this->assertStringContainsString('HTTP_AUTHORIZATION', (string) file_get_contents(base_path('public/.htaccess')));
    }

    public function test_version_fields_match_the_version_file(): void
    {
        $version = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertStringContainsString("APP_VERSION={$version}\n", str_replace("\r\n", "\n", (string) file_get_contents(base_path('.env.example'))));
        $this->assertStringContainsString("env('APP_VERSION', '{$version}')", (string) file_get_contents(base_path('config/app.php')));
        $this->assertArrayNotHasKey('APP_VERSION', $this->herdEnv());
    }
}
