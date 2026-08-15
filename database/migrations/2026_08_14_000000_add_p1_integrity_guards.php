<?php

use App\Services\DataIntegrityAuditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TRIGGERS = [
        'academic_years_integrity_insert',
        'academic_years_integrity_update',
        'timetable_entries_times_insert',
        'timetable_entries_times_update',
        'payments_amount_insert',
        'payments_amount_update',
        'payment_lines_amount_insert',
        'payment_lines_amount_update',
    ];

    public function up(): void
    {
        $report = app(DataIntegrityAuditService::class)->run();

        if (! $report['safe_for_constraints']) {
            $keys = collect($report['checks'])
                ->where('severity', 'blocking')
                ->where('count', '>', 0)
                ->pluck('key')
                ->implode(', ');

            throw new RuntimeException(
                'Migration P1 refusée : '.$report['blocker_count'].' anomalie(s) bloquante(s). '.
                'Exécutez php artisan lpp:audit-data-integrity. Contrôles : '.$keys,
            );
        }

        $this->addGeneratedGuards();
        $this->addUniqueIndexes();
        $this->createValidationTriggers();
    }

    public function down(): void
    {
        $this->removeValidationTriggers();
        $this->removeUniqueIndexes();
        $this->removeGeneratedGuards();
    }

    private function addGeneratedGuards(): void
    {
        if (! Schema::hasColumn('academic_years', 'integrity_active_guard')) {
            Schema::table('academic_years', function (Blueprint $table): void {
                $table->unsignedTinyInteger('integrity_active_guard')
                    ->nullable()
                    ->virtualAs('CASE WHEN is_active = 1 THEN 1 ELSE NULL END');
            });
        }

        if (! Schema::hasColumn('guardian_student', 'integrity_primary_student_id')) {
            Schema::table('guardian_student', function (Blueprint $table): void {
                $table->unsignedBigInteger('integrity_primary_student_id')
                    ->nullable()
                    ->virtualAs('CASE WHEN is_primary = 1 THEN student_id ELSE NULL END');
            });
        }

        if (! Schema::hasColumn('guardian_student', 'integrity_exclusive_relationship')) {
            Schema::table('guardian_student', function (Blueprint $table): void {
                $table->string('integrity_exclusive_relationship', 20)
                    ->nullable()
                    ->virtualAs("CASE WHEN relationship IN ('father', 'mother', 'tutor') THEN relationship ELSE NULL END");
            });
        }
    }

    private function addUniqueIndexes(): void
    {
        $this->addUniqueIndexIfMissing(
            'academic_years',
            ['integrity_active_guard'],
            'academic_years_single_active_unique',
        );

        $this->addUniqueIndexIfMissing(
            'guardian_student',
            ['integrity_primary_student_id'],
            'guardian_student_single_primary_unique',
        );
        $this->ensureGuardianStudentForeignKeyIndex();
        $this->addUniqueIndexIfMissing(
            'guardian_student',
            ['student_id', 'integrity_exclusive_relationship'],
            'guardian_student_relationship_unique',
        );

        $this->addUniqueIndexIfMissing(
            'timetable_entries',
            ['timetable_id', 'day_of_week', 'sort_order'],
            'timetable_entries_cell_unique',
        );
        $this->addUniqueIndexIfMissing(
            'timetable_entries',
            ['teacher_id', 'day_of_week', 'timetable_period_id'],
            'timetable_entries_teacher_slot_unique',
        );
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addUniqueIndexIfMissing(string $tableName, array $columns, string $indexName): void
    {
        if (Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }

    private function ensureGuardianStudentForeignKeyIndex(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
            || Schema::hasIndex('guardian_student', 'guardian_student_student_id_index')) {
            return;
        }

        Schema::table('guardian_student', function (Blueprint $table): void {
            $table->index('student_id', 'guardian_student_student_id_index');
        });
    }

    private function createValidationTriggers(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite' => $this->createSqliteTriggers(),
            'mysql', 'mariadb' => $this->createMySqlTriggers(),
            default => throw new RuntimeException('La migration P1 ne prend en charge que MySQL, MariaDB et SQLite.'),
        };
    }

    private function createSqliteTriggers(): void
    {
        $this->createSqliteTrigger(
            'academic_years_integrity_insert',
            'academic_years',
            'INSERT',
            $this->sqliteAcademicYearCondition(),
            'Année scolaire incohérente ou chevauchante.',
        );
        $this->createSqliteTrigger(
            'academic_years_integrity_update',
            'academic_years',
            'UPDATE',
            $this->sqliteAcademicYearCondition(),
            'Année scolaire incohérente ou chevauchante.',
        );
        $this->createSqliteTrigger(
            'timetable_entries_times_insert',
            'timetable_entries',
            'INSERT',
            'NEW.is_break = 0 AND NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at',
            'Les heures du cours sont invalides.',
        );
        $this->createSqliteTrigger(
            'timetable_entries_times_update',
            'timetable_entries',
            'UPDATE',
            'NEW.is_break = 0 AND NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at',
            'Les heures du cours sont invalides.',
        );
        $this->createSqliteTrigger('payments_amount_insert', 'payments', 'INSERT', 'NEW.amount <= 0', 'Le montant du paiement doit être positif.');
        $this->createSqliteTrigger('payments_amount_update', 'payments', 'UPDATE', 'NEW.amount <= 0', 'Le montant du paiement doit être positif.');
        $this->createSqliteTrigger('payment_lines_amount_insert', 'payment_lines', 'INSERT', 'NEW.amount <= 0', 'Le montant de la ligne doit être positif.');
        $this->createSqliteTrigger('payment_lines_amount_update', 'payment_lines', 'UPDATE', 'NEW.amount <= 0', 'Le montant de la ligne doit être positif.');
    }

    private function createSqliteTrigger(
        string $name,
        string $table,
        string $event,
        string $condition,
        string $message,
    ): void {
        $escapedMessage = str_replace("'", "''", $message);

        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE {$event} ON {$table} ".
            "FOR EACH ROW WHEN {$condition} BEGIN ".
            "SELECT RAISE(ABORT, '{$escapedMessage}'); END",
        );
    }

    private function sqliteAcademicYearCondition(): string
    {
        return "NEW.starts_at >= NEW.ends_at
            OR (NEW.is_active = 1 AND NEW.status <> 'active')
            OR (NEW.is_active = 0 AND NEW.status = 'active')
            OR EXISTS (
                SELECT 1 FROM academic_years
                WHERE id <> COALESCE(NEW.id, -1)
                  AND starts_at <= NEW.ends_at
                  AND ends_at >= NEW.starts_at
            )";
    }

    private function createMySqlTriggers(): void
    {
        $academicCondition = "NEW.starts_at >= NEW.ends_at
            OR (NEW.is_active = 1 AND NEW.status <> 'active')
            OR (NEW.is_active = 0 AND NEW.status = 'active')
            OR EXISTS (
                SELECT 1 FROM academic_years
                WHERE id <> COALESCE(NEW.id, 0)
                  AND starts_at <= NEW.ends_at
                  AND ends_at >= NEW.starts_at
            )";

        $this->createMySqlTrigger(
            'academic_years_integrity_insert',
            'academic_years',
            'INSERT',
            $academicCondition,
            'Annee scolaire incoherente ou chevauchante.',
        );
        $this->createMySqlTrigger(
            'academic_years_integrity_update',
            'academic_years',
            'UPDATE',
            $academicCondition,
            'Annee scolaire incoherente ou chevauchante.',
        );
        $this->createMySqlTrigger(
            'timetable_entries_times_insert',
            'timetable_entries',
            'INSERT',
            'NEW.is_break = 0 AND NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at',
            'Les heures du cours sont invalides.',
        );
        $this->createMySqlTrigger(
            'timetable_entries_times_update',
            'timetable_entries',
            'UPDATE',
            'NEW.is_break = 0 AND NEW.starts_at IS NOT NULL AND NEW.ends_at IS NOT NULL AND NEW.starts_at >= NEW.ends_at',
            'Les heures du cours sont invalides.',
        );
        $this->createMySqlTrigger('payments_amount_insert', 'payments', 'INSERT', 'NEW.amount <= 0', 'Le montant du paiement doit etre positif.');
        $this->createMySqlTrigger('payments_amount_update', 'payments', 'UPDATE', 'NEW.amount <= 0', 'Le montant du paiement doit etre positif.');
        $this->createMySqlTrigger('payment_lines_amount_insert', 'payment_lines', 'INSERT', 'NEW.amount <= 0', 'Le montant de la ligne doit etre positif.');
        $this->createMySqlTrigger('payment_lines_amount_update', 'payment_lines', 'UPDATE', 'NEW.amount <= 0', 'Le montant de la ligne doit etre positif.');
    }

    private function createMySqlTrigger(
        string $name,
        string $table,
        string $event,
        string $condition,
        string $message,
    ): void {
        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE {$event} ON {$table} FOR EACH ROW ".
            "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END",
        );
    }

    private function removeValidationTriggers(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function removeUniqueIndexes(): void
    {
        $this->ensureGuardianStudentForeignKeyIndex();

        $indexes = [
            'academic_years' => ['academic_years_single_active_unique'],
            'guardian_student' => [
                'guardian_student_single_primary_unique',
                'guardian_student_relationship_unique',
            ],
            'timetable_entries' => [
                'timetable_entries_cell_unique',
                'timetable_entries_teacher_slot_unique',
            ],
        ];

        foreach ($indexes as $tableName => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                if (Schema::hasIndex($tableName, $index)) {
                    Schema::table($tableName, function (Blueprint $table) use ($index): void {
                        $table->dropUnique($index);
                    });
                }
            }
        }
    }

    private function removeGeneratedGuards(): void
    {
        foreach ([
            'academic_years' => ['integrity_active_guard'],
            'guardian_student' => [
                'integrity_primary_student_id',
                'integrity_exclusive_relationship',
            ],
        ] as $tableName => $columns) {
            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn($tableName, $column),
            ));

            if ($existing !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($existing): void {
                    $table->dropColumn($existing);
                });
            }
        }
    }
};
