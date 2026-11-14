<?php

namespace Tests\Unit\Package;

use PHPUnit\Framework\TestCase;

class PackageCodeConstraintsTest extends TestCase
{
    public function test_package_code_does_not_reference_laravel_classes(): void
    {
        $dir = dirname(__DIR__, 3).'/install/package';
        foreach (glob($dir.'/*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('Illuminate\\', $source, $path);
            $this->assertStringNotContainsString('App\\', $source, $path);
            $this->assertStringNotContainsString('Laravel\\', $source, $path);
        }
    }
}
