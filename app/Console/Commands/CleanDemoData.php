<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanDemoData extends Command
{
    protected $signature = 'lpp:clean-demo-data
        {--force : Supprimer définitivement les données après les contrôles}';

    protected $description = 'Contrôler puis supprimer les données de démonstration avant la remise au client';

    private const TABLES_TO_EMPTY = [
        'communication_email_events',
        'communication_messages',
        'communication_campaigns',
        'mock_exam_scores',
        'mock_exam_candidates',
        'mock_exam_subjects',
        'mock_exam_classes',
        'mock_exams',
        'payment_lines',
        'payments',
        'grades',
        'report_cards',
        'assessments',
        'attendance_records',
        'attendance_sessions',
        'student_exit_authorizations',
        'disciplinary_records',
        'student_documents',
        'guardian_student',
        'enrollments',
        'guardians',
        'students',
        'expenses',
        'teacher_fee_lines',
        'teacher_fee_statements',
        'teacher_work_sessions',
        'teacher_documents',
        'teacher_availabilities',
        'teacher_availability_schedules',
        'timetable_entries',
        'timetables',
        'timetable_generation_runs',
        'media',
        'activity_log',
        'activity_logs',
        'login_histories',
        'personal_access_tokens',
        'password_reset_tokens',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache_locks',
        'cache',
    ];

    private const PRESERVED_TABLES = [
        'users',
        'roles',
        'permissions',
        'school_settings',
        'academic_years',
        'terms',
        'term_periods',
        'levels',
        'academic_tracks',
        'subjects',
        'school_classes',
        'class_subjects',
        'fee_types',
        'fee_schedules',
        'assessment_types',
        'required_student_documents',
        'numbering_settings',
        'timetable_periods',
        'communication_templates',
    ];

    public function handle(): int
    {
        $students = DB::table('students')->select(['id', 'matricule'])->orderBy('id')->get();
        $unknownStudents = $students->reject(
            fn (object $student): bool => $this->isKnownDemoMatricule((string) $student->matricule),
        );
        $demoUserIds = $this->demoUserIds();
        $uploadedFiles = $this->uploadedFileCount();

        $this->newLine();
        $this->info('Aperçu du nettoyage de remise au client');
        $this->table(
            ['Données à supprimer', 'Nombre'],
            collect(self::TABLES_TO_EMPTY)
                ->filter(fn (string $table): bool => Schema::hasTable($table))
                ->map(fn (string $table): array => [$table, DB::table($table)->count()])
                ->filter(fn (array $line): bool => $line[1] > 0)
                ->values()
                ->all(),
        );
        $this->line('Comptes « Professeur démo » à supprimer : '.$demoUserIds->count());
        $this->line('Fichiers téléversés référencés : '.$uploadedFiles);

        if ($unknownStudents->isNotEmpty()) {
            $this->error('Nettoyage bloqué : des élèves ne correspondent pas aux jeux de démonstration connus.');
            $this->table(
                ['ID', 'Matricule'],
                $unknownStudents->map(fn (object $student): array => [$student->id, $student->matricule])->all(),
            );

            return self::FAILURE;
        }

        if ($uploadedFiles > 0) {
            $this->error('Nettoyage bloqué : des fichiers téléversés sont présents. Ils doivent être sauvegardés et supprimés avec leur stockage.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('Aucune donnée n’a été supprimée. Relancez la commande avec --force après avoir vérifié une sauvegarde SQL récente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($demoUserIds): void {
            DB::table('school_classes')->whereIn('main_teacher_id', $demoUserIds)->update(['main_teacher_id' => null]);
            DB::table('class_subjects')->whereIn('teacher_id', $demoUserIds)->update(['teacher_id' => null]);

            foreach (self::TABLES_TO_EMPTY as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            if ($demoUserIds->isNotEmpty()) {
                DB::table('teacher_profiles')->whereIn('user_id', $demoUserIds)->delete();
                DB::table('model_has_permissions')->whereIn('model_id', $demoUserIds)->delete();
                DB::table('model_has_roles')->whereIn('model_id', $demoUserIds)->delete();
                DB::table('users')->whereIn('id', $demoUserIds)->delete();
            }

            DB::table('numbering_settings')->update([
                'next_number' => 1,
                'updated_at' => now(),
            ]);
        });

        $this->newLine();
        $this->info('Les données de démonstration ont été supprimées.');
        $this->table(
            ['Configuration conservée', 'Nombre'],
            collect(self::PRESERVED_TABLES)
                ->filter(fn (string $table): bool => Schema::hasTable($table))
                ->map(fn (string $table): array => [$table, DB::table($table)->count()])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }

    private function demoUserIds(): Collection
    {
        return DB::table('users')
            ->where(function ($query): void {
                $query->where('username', 'like', 'demo.edt.%')
                    ->orWhere('email', 'like', '%@example.invalid');
            })
            ->pluck('id');
    }

    private function uploadedFileCount(): int
    {
        $count = 0;

        foreach ([
            ['student_documents', 'file_path'],
            ['teacher_documents', 'file_path'],
            ['report_cards', 'pdf_path'],
            ['students', 'photo_path'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $count += DB::table($table)->whereNotNull($column)->where($column, '<>', '')->count();
            }
        }

        if (Schema::hasTable('media')) {
            $count += DB::table('media')->count();
        }

        return $count;
    }

    private function isKnownDemoMatricule(string $matricule): bool
    {
        if ($matricule === 'LPP-2026-0162') {
            return true;
        }

        return preg_match('/^TEST-2026-000[12]$/', $matricule) === 1
            || preg_match('/^LPP-DEMO-[A-Z0-9-]+-(?:0[1-9]|10)$/', $matricule) === 1
            || preg_match('/^LPP-2026-3E-(?:00[1-9]|010)$/', $matricule) === 1
            || preg_match('/^LPP-2026-TLE-00[1-3]$/', $matricule) === 1;
    }
}
