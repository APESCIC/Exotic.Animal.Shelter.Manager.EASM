<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReadmeHostingTest extends TestCase
{
    public function test_readme_documents_nginx_front_controller(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('try_files $uri $uri/ /index.php?$query_string;', $readme);
        $this->assertStringContainsString('fastcgi_pass', $readme);
        $this->assertStringContainsString('public/.htaccess', $readme);
    }
}
