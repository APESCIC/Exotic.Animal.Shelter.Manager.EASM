<?php

namespace App\Http\Controllers;

use App\Support\LocalRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class HealthController extends Controller
{
    /**
     * Seconds to wait when opening a MySQL/MariaDB connection for /health.
     */
    public const DATABASE_CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * Report application health and version for hosting checks.
     */
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseIsReachable();

        $body = [
            'status' => $database ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'version' => config('app.version'),
            'checks' => [
                'app' => true,
                'database' => $database,
            ],
        ];

        // The live preview gate checks the Herd preview serves this checkout through FastCGI.
        if (app()->environment('local')) {
            $body['local'] = [
                'php' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'git_revision' => (new LocalRevision(base_path()))->current(),
            ];
        }

        return response()->json($body, $database ? 200 : 503);
    }

    private function databaseIsReachable(): bool
    {
        try {
            $this->applyMysqlConnectTimeout();
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function applyMysqlConnectTimeout(): void
    {
        $name = DB::getDefaultConnection();
        $driver = config("database.connections.{$name}.driver");

        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        $options = config("database.connections.{$name}.options", []);
        if (! is_array($options)) {
            $options = [];
        }

        if (array_key_exists(PDO::ATTR_TIMEOUT, $options) && $options[PDO::ATTR_TIMEOUT] !== null) {
            return;
        }

        config([
            "database.connections.{$name}.options" => $options + [
                PDO::ATTR_TIMEOUT => self::DATABASE_CONNECT_TIMEOUT_SECONDS,
            ],
        ]);
    }
}
