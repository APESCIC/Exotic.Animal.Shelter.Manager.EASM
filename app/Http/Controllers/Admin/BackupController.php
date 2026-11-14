<?php

namespace App\Http\Controllers\Admin;

use App\Backup\BackupException;
use App\Backup\BackupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backups): View
    {
        return view('admin.backups.index', [
            'backups' => $backups->list(),
        ]);
    }

    public function download(string $backup, BackupService $backups): BinaryFileResponse
    {
        try {
            $zip = $backups->downloadZip($backup);
        } catch (BackupException $e) {
            abort(404, $e->getMessage());
        }

        return response()->download($zip, 'easm-backup-'.$backup.'.zip')->deleteFileAfterSend(true);
    }

    public function store(BackupService $backups): RedirectResponse
    {
        $id = $backups->create();

        return redirect()
            ->route('admin.backups.index')
            ->with('status', 'Backup '.$id.' created.');
    }
}
