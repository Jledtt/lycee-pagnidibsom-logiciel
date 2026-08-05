<?php

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\DatabaseBackupService;
use App\Services\PagnidibsomClassSubjectSetupService;
use App\Services\TariffDefaultService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

Artisan::command('lpp:clean-demo-data', function () {
    $matricules = ['TEST-2026-0001', 'TEST-2026-0002'];
    $students = Student::withTrashed()
        ->whereIn('matricule', $matricules)
        ->get();

    if ($students->isEmpty()) {
        $this->info('Aucune donnee de test a nettoyer.');

        return;
    }

    $studentIds = $students->pluck('id');
    $guardianIds = DB::table('guardian_student')
        ->whereIn('student_id', $studentIds)
        ->pluck('guardian_id');
    $classIds = Enrollment::query()
        ->whereIn('student_id', $studentIds)
        ->pluck('school_class_id')
        ->filter()
        ->unique();
    $paymentIds = Payment::query()->whereIn('student_id', $studentIds)->pluck('id');
    $attendanceSessionIds = AttendanceRecord::query()
        ->whereIn('student_id', $studentIds)
        ->pluck('attendance_session_id');
    $assessmentIds = Assessment::query()
        ->whereIn('school_class_id', $classIds)
        ->where('title', 'like', '%Test MySQL%')
        ->pluck('id');

    DB::transaction(function () use ($studentIds, $guardianIds, $classIds, $paymentIds, $students, $attendanceSessionIds, $assessmentIds) {
        PaymentLine::query()->whereIn('payment_id', $paymentIds)->delete();
        Payment::query()->whereIn('id', $paymentIds)->delete();
        Grade::query()->whereIn('assessment_id', $assessmentIds)->delete();
        Assessment::query()->whereIn('id', $assessmentIds)->delete();
        Grade::query()->whereIn('student_id', $studentIds)->delete();
        ReportCard::query()->whereIn('student_id', $studentIds)->delete();
        AttendanceRecord::query()->whereIn('student_id', $studentIds)->delete();
        AttendanceSession::query()
            ->whereIn('id', $attendanceSessionIds)
            ->whereDoesntHave('records')
            ->delete();
        Enrollment::query()->whereIn('student_id', $studentIds)->delete();

        foreach ($students as $student) {
            $student->guardians()->detach();
            $student->forceDelete();
        }

        Guardian::query()
            ->whereIn('id', $guardianIds)
            ->whereDoesntHave('students')
            ->delete();

        FeeSchedule::query()->whereIn('school_class_id', $classIds)->delete();
        ClassSubject::query()->whereIn('school_class_id', $classIds)->delete();
        SchoolClass::query()
            ->whereIn('id', $classIds)
            ->whereDoesntHave('enrollments')
            ->delete();
    });

    $this->info('Données de test Awa/Issa supprimées.');
})->purpose('Supprimer les élèves de démonstration TEST-2026');

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
    $this->line('- Lignes creees ou mises a jour : '.$result['lines']);
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
