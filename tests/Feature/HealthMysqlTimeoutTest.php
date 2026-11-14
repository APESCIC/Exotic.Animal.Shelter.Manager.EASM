<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HealthMysqlTimeoutTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function mysqlFamilyDrivers(): array
    {
        return [
            'mysql' => ['mysql'],
            'mariadb' => ['mariadb'],
        ];
    }

    #[DataProvider('mysqlFamilyDrivers')]
    public function test_mysql_family_health_check_applies_a_short_connect_timeout(string $driver): void
    {
        config([
            'database.default' => $driver,
            "database.connections.{$driver}.host" => '127.0.0.1',
            "database.connections.{$driver}.port" => 1,
            "database.connections.{$driver}.database" => 'easm',
            "database.connections.{$driver}.username" => 'easm',
            "database.connections.{$driver}.password" => 'secret',
        ]);
        DB::purge();

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database', false);

        $this->assertSame(
            HealthController::DATABASE_CONNECT_TIMEOUT_SECONDS,
            config("database.connections.{$driver}.options")[PDO::ATTR_TIMEOUT]
        );
    }
}
