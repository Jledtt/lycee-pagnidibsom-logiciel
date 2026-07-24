<?php

use Illuminate\Foundation\Inspiring;
use App\Services\DatabaseBackupService;
use App\Services\PagnidibsomClassSubjectSetupService;
use App\Services\TariffDefaultService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lpp:backup-database {--path=}', function () {
    $backup = app(DatabaseBackupService::class)->create($this->option('path'));

    $this->info('Sauvegarde JSON creee : ' . $backup['json_path']);

    if ($backup['native_path']) {
        $this->info('Sauvegarde native creee : ' . $backup['native_path']);
    }

    if ($backup['archive_path']) {
        $this->info('Archive telechargeable creee : ' . $backup['archive_path']);
    }
})->purpose('Sauvegarder la base de donnees LPP');

Artisan::command('lpp:clean-demo-data', function () {
    $matricules = ['TEST-2026-0001', 'TEST-2026-0002'];
    $students = \App\Models\Student::withTrashed()
        ->whereIn('matricule', $matricules)
        ->get();

    if ($students->isEmpty()) {
        $this->info('Aucune donnee de test a nettoyer.');

        return;
    }

    $studentIds = $students->pluck('id');
    $guardianIds = \Illuminate\Support\Facades\DB::table('guardian_student')
        ->whereIn('student_id', $studentIds)
        ->pluck('guardian_id');
    $classIds = \App\Models\Enrollment::query()
        ->whereIn('student_id', $studentIds)
        ->pluck('school_class_id')
        ->filter()
        ->unique();
    $paymentIds = \App\Models\Payment::query()->whereIn('student_id', $studentIds)->pluck('id');
    $attendanceSessionIds = \App\Models\AttendanceRecord::query()
        ->whereIn('student_id', $studentIds)
        ->pluck('attendance_session_id');
    $assessmentIds = \App\Models\Assessment::query()
        ->whereIn('school_class_id', $classIds)
        ->where('title', 'like', '%Test MySQL%')
        ->pluck('id');

    \Illuminate\Support\Facades\DB::transaction(function () use ($studentIds, $guardianIds, $classIds, $paymentIds, $students, $attendanceSessionIds, $assessmentIds) {
        \App\Models\PaymentLine::query()->whereIn('payment_id', $paymentIds)->delete();
        \App\Models\Payment::query()->whereIn('id', $paymentIds)->delete();
        \App\Models\Grade::query()->whereIn('assessment_id', $assessmentIds)->delete();
        \App\Models\Assessment::query()->whereIn('id', $assessmentIds)->delete();
        \App\Models\Grade::query()->whereIn('student_id', $studentIds)->delete();
        \App\Models\ReportCard::query()->whereIn('student_id', $studentIds)->delete();
        \App\Models\AttendanceRecord::query()->whereIn('student_id', $studentIds)->delete();
        \App\Models\AttendanceSession::query()
            ->whereIn('id', $attendanceSessionIds)
            ->whereDoesntHave('records')
            ->delete();
        \App\Models\Enrollment::query()->whereIn('student_id', $studentIds)->delete();

        foreach ($students as $student) {
            $student->guardians()->detach();
            $student->forceDelete();
        }

        \App\Models\Guardian::query()
            ->whereIn('id', $guardianIds)
            ->whereDoesntHave('students')
            ->delete();

        \App\Models\FeeSchedule::query()->whereIn('school_class_id', $classIds)->delete();
        \App\Models\ClassSubject::query()->whereIn('school_class_id', $classIds)->delete();
        \App\Models\SchoolClass::query()
            ->whereIn('id', $classIds)
            ->whereDoesntHave('enrollments')
            ->delete();
    });

    $this->info('Donnees de test Awa/Issa supprimees.');
})->purpose('Supprimer les eleves de demonstration TEST-2026');

Artisan::command('lpp:setup-classes-subjects', function () {
    $result = app(PagnidibsomClassSubjectSetupService::class)->apply();

    $this->info('Configuration appliquee pour ' . $result['academic_year'] . '.');

    foreach ($result['classes'] as $line) {
        $this->line('- ' . $line['class'] . ' : ' . $line['subjects'] . ' matiere(s)');
    }
})->purpose('Creer les classes et rattacher les matieres LPP');

Artisan::command('lpp:setup-tariffs', function () {
    $result = app(TariffDefaultService::class)->applyToActiveAcademicYear();

    $this->info('Tarifs appliques pour ' . $result['academic_year'] . '.');
    $this->line('- Classes traitees : ' . $result['classes']);
    $this->line('- Lignes creees ou mises a jour : ' . $result['lines']);
})->purpose('Appliquer les tarifs officiels LPP aux classes actives');

Schedule::command('lpp:backup-database')
    ->dailyAt(env('LPP_BACKUP_TIME', '22:00'))
    ->withoutOverlapping();
