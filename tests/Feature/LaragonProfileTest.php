<?php

namespace Tests\Feature;

use Tests\TestCase;

class LaragonProfileTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return json_decode((string) file_get_contents(base_path('docs/laragon/profile.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function laragonEnv(): array
    {
        $values = [];
        foreach (file(base_path('.env.laragon.example'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
                $values[$match[1]] = trim($match[2], '"');
            }
        }

        return $values;
    }

    public function test_the_laragon_env_example_matches_the_profile(): void
    {
        $profile = $this->profile();
        $env = $this->laragonEnv();

        $this->assertSame('local', $env['APP_ENV']);
        $this->assertSame($profile['appUrl'], $env['APP_URL']);
        $this->assertSame('http://'.$profile['hostname'], $env['APP_URL']);
        $this->assertSame($profile['database']['host'], $env['DB_HOST']);
        $this->assertSame($profile['database']['name'], $env['DB_DATABASE']);
        $this->assertSame($profile['database']['username'], $env['DB_USERNAME']);
        $this->assertSame('', $env['DB_PASSWORD']);
        $this->assertStringEndsWith('_local', $env['DB_DATABASE']);
    }

    public function test_the_shipped_env_example_stays_production_safe(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString("APP_ENV=production\n", str_replace("\r\n", "\n", $example));
        $this->assertStringContainsString("APP_DEBUG=false\n", str_replace("\r\n", "\n", $example));
    }

    public function test_the_folder_and_live_reload_port_match_the_profile(): void
    {
        $profile = $this->profile();
        $package = (string) file_get_contents(base_path('package.json'));

        $this->assertSame($profile['project'].'.test', $profile['hostname']);
        $this->assertSame('C:\\laragon\\www\\'.$profile['project'], $profile['folder']);
        $this->assertStringContainsString('--proxy '.$profile['appUrl'].' --port '.$profile['devServerPort'], $package);
    }

    public function test_the_vhost_runs_the_profile_php_through_fastcgi(): void
    {
        $profile = $this->profile();
        $vhost = (string) file_get_contents(base_path($profile['siteVhost']));

        $this->assertSame('fcgid', $profile['apacheHandler']);
        $this->assertStringContainsString('define EASM_PHP "C:/laragon/bin/php/'.$profile['phpBuild'].'"', $vhost);
        $this->assertStringContainsString('FcgidWrapper "${EASM_PHP}/php-cgi.exe" .php', $vhost);
        $this->assertStringContainsString('define ROOT "C:/laragon/www/'.$profile['project'].'/public"', $vhost);
        $this->assertStringStartsWith('php-'.$profile['php'].'.', $profile['phpBuild']);
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
        $manifest = json_decode((string) file_get_contents(base_path('CloudronManifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertSame($version, $manifest['version']);
        $this->assertStringContainsString("env('APP_VERSION', '{$version}')", (string) file_get_contents(base_path('config/app.php')));
        $this->assertSame($version, $this->laragonEnv()['APP_VERSION']);
    }
}
