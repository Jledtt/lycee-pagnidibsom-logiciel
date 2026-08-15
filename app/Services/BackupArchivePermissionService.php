<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupArchivePermissionService
{
    private const FILE_MODE = 0640;

    public function secure(string $diskName, string $backupName): void
    {
        if ($diskName !== 'local') {
            return;
        }

        $disk = Storage::disk($diskName);

        foreach ($disk->files($backupName) as $file) {
            if (! str_ends_with(strtolower($file), '.zip')) {
                continue;
            }

            $path = $disk->path($file);

            if (! chmod($path, self::FILE_MODE)) {
                throw new RuntimeException("Impossible de protéger l'archive de sauvegarde {$file}.");
            }
        }
    }
}
