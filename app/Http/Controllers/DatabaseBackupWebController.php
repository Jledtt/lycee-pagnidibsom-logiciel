<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupWebController extends Controller
{
    public function index(DatabaseBackupService $backupService): View
    {
        return view('settings.backups', [
            'backups' => $backupService->list(),
            'directory' => $backupService->directory(),
        ]);
    }

    public function store(Request $request, DatabaseBackupService $backupService): RedirectResponse
    {
        $backup = $backupService->create();
        $recommendedPath = $backup['archive_path'] ?: $backup['native_path'] ?: $backup['json_path'];

        return redirect()
            ->route('settings.backups.index')
            ->with('success', 'Sauvegarde créée : '.basename($recommendedPath));
    }

    public function download(string $filename, DatabaseBackupService $backupService): BinaryFileResponse|Response
    {
        $path = $backupService->pathForDownload($filename);

        abort_if(! $path, 404);

        return response()->download($path);
    }
}
