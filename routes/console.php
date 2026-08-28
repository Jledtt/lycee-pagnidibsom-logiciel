<?php

use App\Services\DatabaseBackupService;
use App\Services\PagnidibsomClassSubjectSetupService;
use App\Services\TariffDefaultService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Backup\Events\BackupHasFailed;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lpp:backup-database {--path=}', function () {
    $backup = app(DatabaseBackupService::class)->create($this->option('path'));

    $this->info('Sauvegarde JSON créée : '.$backup['json_path']);

    if ($backup['native_path']) {
        $this->info('Sauvegarde native créée : '.$backup['native_path']);
    }

    if ($backup['archive_path']) {
        $this->info('Archive téléchargeable créée : '.$backup['archive_path']);
    }
})->purpose('Sauvegarder la base de données LPP');

Artisan::command('lpp:test-backup-alert', function () {
    event(new BackupHasFailed(
        new RuntimeException('[TEST] Vérification du canal d’alerte des sauvegardes LPP.'),
        'local',
        config('backup.backup.name'),
    ));

    $this->info('Alerte de test transmise au canal de notification configuré.');
})->purpose('Tester l’alerte par e-mail des sauvegardes');

Artisan::command('lpp:notify-backup-restore-failure', function () {
    event(new BackupHasFailed(
        new RuntimeException('La vérification périodique de restauration LPP a échoué. Consultez storage/logs/backup-restore-check.log.'),
        'local',
        config('backup.backup.name'),
    ));

    $this->error('Alerte de restauration transmise.');
})->purpose('Signaler un échec de vérification de restauration');

Artisan::command('lpp:setup-classes-subjects', function () {
    $result = app(PagnidibsomClassSubjectSetupService::class)->apply();

    $this->info('Configuration appliquée pour '.$result['academic_year'].'.');

    foreach ($result['classes'] as $line) {
        $this->line('- '.$line['class'].' : '.$line['subjects'].' matière(s)');
    }
})->purpose('Créer les classes et rattacher les matières LPP');

Artisan::command('lpp:setup-tariffs', function () {
    $result = app(TariffDefaultService::class)->applyToActiveAcademicYear();

    $this->info('Tarifs appliques pour '.$result['academic_year'].'.');
    $this->line('- Classes traitees : '.$result['classes']);
    $this->line('- Lignes créées ou mises à jour : '.$result['lines']);
})->purpose('Appliquer les tarifs officiels LPP aux classes actives');

Schedule::command('backup:run')
    ->dailyAt(env('LPP_BACKUP_TIME', '22:00'))
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->dailyAt(env('LPP_BACKUP_MONITOR_TIME', '22:15'))
    ->withoutOverlapping();

Schedule::command('backup:clean')
    ->dailyAt(env('LPP_BACKUP_CLEAN_TIME', '22:30'))
    ->withoutOverlapping();
