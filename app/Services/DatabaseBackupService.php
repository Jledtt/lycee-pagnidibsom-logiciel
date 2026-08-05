<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseBackupService
{
    private const DIRECTORY_MODE = 02750;

    private const FILE_MODE = 0640;

    public function create(?string $directory = null): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = $config['driver'] ?? $connection;
        $database = $config['database'] ?? null;
        $directory = $directory ?: $this->directory();
        $timestamp = now()->format('Ymd-His');

        $this->secureDirectory($directory);

        $jsonPath = $directory.DIRECTORY_SEPARATOR."lpp-{$driver}-{$timestamp}.json";
        File::put($jsonPath, json_encode([
            'application' => 'LPP Gestion Scolaire',
            'connection' => $connection,
            'driver' => $driver,
            'database' => $database,
            'generated_at' => now()->toIso8601String(),
            'tables' => $this->tableRows($driver, $database),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $nativePath = $this->createNativeBackup($driver, $config, $directory, $timestamp);
        $archivePath = $this->createArchive($directory, $driver, $timestamp, array_filter([$jsonPath, $nativePath]));

        foreach (array_filter([$jsonPath, $nativePath, $archivePath]) as $path) {
            $this->secureFile($path);
        }

        $this->prune($directory);

        return [
            'connection' => $connection,
            'driver' => $driver,
            'json_path' => $jsonPath,
            'native_path' => $nativePath,
            'archive_path' => $archivePath,
        ];
    }

    public function list(): Collection
    {
        $this->secureDirectory($this->directory());

        return collect(File::glob($this->directory().DIRECTORY_SEPARATOR.'lpp-*') ?: [])
            ->filter(fn (string $path) => File::isFile($path))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'path' => $path,
                'size' => File::size($path),
                'created_at' => File::lastModified($path),
                'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function pathForDownload(string $filename): ?string
    {
        $filename = basename($filename);
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        if (! str_starts_with($filename, 'lpp-') || ! File::exists($path) || ! File::isFile($path)) {
            return null;
        }

        return $path;
    }

    public function directory(): string
    {
        return config('lpp.backup.export_path') ?: storage_path('app/backups');
    }

    private function tableRows(string $driver, ?string $database): array
    {
        $tables = $this->tables($driver, $database);

        if ($tables->isEmpty()) {
            throw new RuntimeException("Sauvegarde non supportée pour la connexion {$driver}.");
        }

        $rows = [];

        foreach ($tables as $table) {
            $rows[$table] = DB::table($table)
                ->get()
                ->map(fn (object $row) => (array) $row)
                ->all();
        }

        return $rows;
    }

    private function tables(string $driver, ?string $database): Collection
    {
        return match ($driver) {
            'sqlite' => collect(DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
                ->pluck('name')
                ->values(),
            'mysql', 'mariadb' => collect(DB::select(
                'select table_name from information_schema.tables where table_schema = ? and table_type = ?',
                [$database, 'BASE TABLE'],
            ))->pluck('TABLE_NAME')
                ->merge(collect(DB::select(
                    'select table_name from information_schema.tables where table_schema = ? and table_type = ?',
                    [$database, 'BASE TABLE'],
                ))->pluck('table_name'))
                ->filter()
                ->unique()
                ->values(),
            'pgsql' => collect(DB::select(
                'select tablename from pg_tables where schemaname = ?',
                ['public'],
            ))->pluck('tablename')->values(),
            default => collect(),
        };
    }

    private function createNativeBackup(string $driver, array $config, string $directory, string $timestamp): ?string
    {
        return match ($driver) {
            'sqlite' => $this->copySqliteDatabase($config, $directory, $timestamp),
            'mysql', 'mariadb' => $this->dumpMysql($config, $directory, $timestamp),
            'pgsql' => $this->dumpPostgres($config, $directory, $timestamp),
            default => null,
        };
    }

    private function copySqliteDatabase(array $config, string $directory, string $timestamp): ?string
    {
        $database = $config['database'] ?? null;

        if ($database === ':memory:' || ! is_string($database) || ! File::exists($database)) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR."lpp-sqlite-{$timestamp}.sqlite";
        File::copy($database, $path);

        return $path;
    }

    private function dumpMysql(array $config, string $directory, string $timestamp): ?string
    {
        $binary = $this->findExecutable(config('lpp.backup.mysqldump_path'), ['mysqldump', 'mysqldump.exe'], [
            'C:/laragon/bin/mysql/*/bin/mysqldump.exe',
        ]);

        if (! $binary) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR."lpp-mysql-{$timestamp}.sql";
        $command = [
            $binary,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? 'root'),
            $config['database'],
        ];

        return $this->runDump($command, $path, [
            'MYSQL_PWD' => $config['password'] ?? '',
        ]);
    }

    private function dumpPostgres(array $config, string $directory, string $timestamp): ?string
    {
        $binary = $this->findExecutable(config('lpp.backup.pg_dump_path'), ['pg_dump', 'pg_dump.exe']);

        if (! $binary) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR."lpp-pgsql-{$timestamp}.sql";
        $command = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 5432),
            '--username='.($config['username'] ?? 'postgres'),
            '--format=plain',
            '--clean',
            '--if-exists',
            $config['database'],
        ];

        return $this->runDump($command, $path, [
            'PGPASSWORD' => $config['password'] ?? '',
        ]);
    }

    private function runDump(array $command, string $path, array $environment = []): ?string
    {
        $process = new Process($command, null, $environment);
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        File::put($path, $process->getOutput());

        return $path;
    }

    private function createArchive(string $directory, string $driver, string $timestamp, array $paths): ?string
    {
        if (! class_exists(ZipArchive::class) || $paths === []) {
            return null;
        }

        $archivePath = $directory.DIRECTORY_SEPARATOR."lpp-{$driver}-{$timestamp}.zip";
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $manifest = [
            'application' => 'LPP Gestion Scolaire',
            'driver' => $driver,
            'generated_at' => now()->toIso8601String(),
            'files' => [],
        ];

        foreach ($paths as $path) {
            if (! is_string($path) || ! File::isFile($path)) {
                continue;
            }

            $name = basename($path);
            $zip->addFile($path, $name);
            $manifest['files'][] = [
                'name' => $name,
                'size' => File::size($path),
                'type' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ];
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return File::isFile($archivePath) ? $archivePath : null;
    }

    private function findExecutable(?string $configured, array $names, array $extraGlobs = []): ?string
    {
        if (is_string($configured) && $configured !== '' && File::exists($configured)) {
            return $configured;
        }

        foreach ($extraGlobs as $glob) {
            $matches = File::glob($glob);

            if ($matches) {
                return $matches[0];
            }
        }

        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        $extensions = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'
            ? array_merge([''], explode(';', (string) getenv('PATHEXT')))
            : [''];

        foreach ($paths as $path) {
            foreach ($names as $name) {
                foreach ($extensions as $extension) {
                    $candidate = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name.$extension;

                    if (File::exists($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function prune(string $directory): void
    {
        $keepDays = max((int) config('lpp.backup.keep_days', 14), 1);

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'lpp-*') ?: [] as $file) {
            if (File::lastModified($file) < now()->subDays($keepDays)->timestamp) {
                File::delete($file);
            }
        }
    }

    private function secureDirectory(string $directory): void
    {
        File::ensureDirectoryExists($directory, self::DIRECTORY_MODE, true);
        @chmod($directory, self::DIRECTORY_MODE);
    }

    private function secureFile(string $path): void
    {
        if (File::isFile($path)) {
            @chmod($path, self::FILE_MODE);
        }
    }
}
