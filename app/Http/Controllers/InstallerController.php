<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallRequest;
use App\Install\DatabaseConnectionException;
use App\Install\Installer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Show the first-run installer wizard.
     */
    public function show(): View
    {
        return view('installer', [
            'timezones' => timezone_identifiers_list(),
            'managedDatabase' => (bool) config('easm.managed_database'),
            'dbDefaults' => [
                'host' => (string) config('database.connections.mysql.host', '127.0.0.1'),
                'port' => (string) config('database.connections.mysql.port', '3306'),
                'database' => (string) config('database.connections.mysql.database', 'easm'),
                'username' => (string) config('database.connections.mysql.username', 'root'),
            ],
        ]);
    }

    /**
     * Complete installation for this shelter.
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
                ->withErrors(['organisation' => 'Installation failed. Check the database credentials and that storage/ is writable.']);
        }

        return redirect('/');
    }
}
