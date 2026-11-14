<?php

namespace App\Http\Controllers;

use App\Install\InstallationState;
use App\Update\UpdateChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Optional web-cron endpoint: GET /cron/update-check?token=…
 */
class UpdateCheckCronController extends Controller
{
    public function __invoke(Request $request, UpdateChecker $checker, InstallationState $installation): JsonResponse
    {
        $configured = (string) config('easm.updates.cron_token', '');
        if ($configured === '') {
            abort(404);
        }

        $provided = (string) $request->query('token', $request->header('X-EASM-Cron-Token', ''));
        if ($provided === '' || ! hash_equals($configured, $provided)) {
            abort(403);
        }

        if (! $installation->isInstalled()) {
            return response()->json([
                'ok' => false,
                'message' => 'EASM is not installed yet.',
            ], 503);
        }

        $status = $checker->check(force: true);

        return response()->json([
            'ok' => $status->ok,
            'update_available' => $status->updateAvailable,
            'latest_version' => $status->latestVersion,
            'current_version' => $status->currentVersion,
            'checked_at' => $status->checkedAt,
            'message' => $status->message,
            'html_url' => $status->htmlUrl,
        ]);
    }
}
