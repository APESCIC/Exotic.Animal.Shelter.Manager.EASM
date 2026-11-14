<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallDatabaseRequest;
use App\Http\Requests\InstallDetailsRequest;
use App\Http\Requests\InstallRequest;
use App\Install\CloudronDatabase;
use App\Install\DatabaseConnectionException;
use App\Install\Installer;
use App\Install\InstallWizard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Show the first-run installer wizard.
     */
    public function show(CloudronDatabase $cloudron): View
    {
        return view('installer', [
            'timezones' => timezone_identifiers_list(),
            'managedDatabase' => (bool) config('easm.managed_database'),
            'cloudronDatabase' => $cloudron->available(),
            // Cloudron's own database details stay off this public page.
            'dbDefaults' => $cloudron->available() ? [
                'host' => '',
                'port' => '3306',
                'database' => '',
                'username' => '',
            ] : [
                'host' => (string) config('database.connections.mysql.host', '127.0.0.1'),
                'port' => (string) config('database.connections.mysql.port', '3306'),
                'database' => (string) config('database.connections.mysql.database', 'easm'),
                'username' => (string) config('database.connections.mysql.username', 'root'),
            ],
            'routes' => [
                'requirements' => route('install.requirements'),
                'database' => route('install.database'),
                'details' => route('install.details'),
                'migrate' => route('install.migrate'),
                'finish' => route('install.finish'),
                'store' => route('install.store'),
            ],
        ]);
    }

    /**
     * Complete installation for this shelter (one-shot form POST).
     */
    public function store(InstallRequest $request, Installer $installer): RedirectResponse
    {
        try {
            $installer->install($request->validated());
        } catch (DatabaseConnectionException $e) {
            return back()
                ->withInput($request->except('admin_password', 'admin_password_confirmation'))
                ->withErrors(['db_host' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput($request->except('admin_password', 'admin_password_confirmation'))
                ->withErrors(['organisation' => $this->safeError($e)]);
        }

        return redirect('/');
    }

    public function requirements(InstallWizard $wizard): JsonResponse
    {
        return response()->json($wizard->requirements());
    }

    public function database(InstallDatabaseRequest $request, InstallWizard $wizard): JsonResponse
    {
        try {
            $result = $wizard->saveDatabase($request->validated());
        } catch (DatabaseConnectionException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $this->safeError($e)], 500);
        }

        return response()->json($result);
    }

    public function details(InstallDetailsRequest $request, InstallWizard $wizard): JsonResponse
    {
        try {
            $result = $wizard->saveDetails($request->validated());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $this->safeError($e)], 500);
        }

        return response()->json($result);
    }

    public function migrate(InstallWizard $wizard): JsonResponse
    {
        try {
            $result = $wizard->migrateStep();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $this->safeError($e)], 500);
        }

        return response()->json(['ok' => true] + $result);
    }

    public function finish(InstallWizard $wizard): JsonResponse
    {
        try {
            $result = $wizard->finish();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $this->safeError($e)], 500);
        }

        return response()->json($result);
    }

    private function safeError(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '' || str_contains(strtolower($message), 'password') || str_contains($message, base_path())) {
            return 'Installation failed. Check the database credentials and that storage/ is writable.';
        }

        return $message;
    }
}
