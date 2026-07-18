<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lpp:backup-database {--path=}', function () {
    $connection = config('database.default');
    $database = config("database.connections.{$connection}.database");
    $driver = config("database.connections.{$connection}.driver");
    $directory = $this->option('path') ?: storage_path('app/backups');
    $timestamp = now()->format('Ymd-His');

    File::ensureDirectoryExists($directory);

    $tables = match ($driver) {
        'sqlite' => collect(DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
            ->pluck('name')
            ->values(),
        'mysql', 'mariadb' => collect(DB::select(
            'select table_name from information_schema.tables where table_schema = ? and table_type = ?',
            [$database, 'BASE TABLE']
        ))->pluck('table_name')->values(),
        'pgsql' => collect(DB::select(
            'select tablename from pg_tables where schemaname = ?',
            ['public']
        ))->pluck('tablename')->values(),
        default => collect(),
    };

    abort_if($tables->isEmpty(), 422, "Sauvegarde non supportee pour la connexion {$driver}.");

    $export = [
        'application' => 'LPP Gestion Scolaire',
        'connection' => $connection,
        'driver' => $driver,
        'generated_at' => now()->toIso8601String(),
        'tables' => [],
    ];

    foreach ($tables as $table) {
        $export['tables'][$table] = DB::table($table)
            ->get()
            ->map(fn (object $row) => (array) $row)
            ->all();
    }

    $jsonPath = $directory . DIRECTORY_SEPARATOR . "lpp-{$driver}-{$timestamp}.json";
    File::put($jsonPath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($driver === 'sqlite' && $database !== ':memory:' && is_string($database) && File::exists($database)) {
        File::copy($database, $directory . DIRECTORY_SEPARATOR . "lpp-sqlite-{$timestamp}.sqlite");
    }

    $keepDays = max((int) env('LPP_BACKUP_KEEP_DAYS', 14), 1);
    foreach (File::glob($directory . DIRECTORY_SEPARATOR . 'lpp-*') as $file) {
        if (File::lastModified($file) < now()->subDays($keepDays)->timestamp) {
            File::delete($file);
        }
    }

    $this->info("Sauvegarde creee : {$jsonPath}");
})->purpose('Sauvegarder la base de donnees LPP');

Schedule::command('lpp:backup-database')
    ->dailyAt(env('LPP_BACKUP_TIME', '22:00'))
    ->withoutOverlapping();
