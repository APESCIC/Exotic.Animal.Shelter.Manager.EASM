<?php

namespace App\Http\Controllers;

use App\Support\LocalRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
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

        // The live preview gate checks the Laragon preview serves this checkout through FastCGI.
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
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
