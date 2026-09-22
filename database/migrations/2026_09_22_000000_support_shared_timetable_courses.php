<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INSERT_TRIGGER = 'timetable_entries_teacher_conflict_insert';

    private const UPDATE_TRIGGER = 'timetable_entries_teacher_conflict_update';

    public function up(): void
    {
        if (! Schema::hasColumn('timetable_entries', 'synchronization_group')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->string('synchronization_group', 80)->nullable()->after('source');
                $table->index('synchronization_group', 'timetable_entries_synchronization_group_index');
            });
        }

        if (Schema::hasIndex('timetable_entries', 'timetable_entries_teacher_slot_unique')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->dropUnique('timetable_entries_teacher_slot_unique');
            });
        }

        $this->createTeacherConflictTriggers();
    }

    public function down(): void
    {
        $duplicates = DB::table('timetable_entries')
            ->whereNotNull('teacher_id')
            ->whereNotNull('timetable_period_id')
            ->select('teacher_id', 'day_of_week', 'timetable_period_id')
            ->groupBy('teacher_id', 'day_of_week', 'timetable_period_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'La migration ne peut pas être annulée tant que des cours communs partagent un professeur et un créneau.',
            );
        }

        $this->dropTeacherConflictTriggers();

        if (Schema::hasIndex('timetable_entries', 'timetable_entries_synchronization_group_index')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->dropIndex('timetable_entries_synchronization_group_index');
            });
        }

        if (Schema::hasColumn('timetable_entries', 'synchronization_group')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->dropColumn('synchronization_group');
            });
        }

        if (! Schema::hasIndex('timetable_entries', 'timetable_entries_teacher_slot_unique')) {
            Schema::table('timetable_entries', function (Blueprint $table): void {
                $table->unique(
                    ['teacher_id', 'day_of_week', 'timetable_period_id'],
                    'timetable_entries_teacher_slot_unique',
                );
            });
        }
    }

    private function createTeacherConflictTriggers(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteTrigger(self::INSERT_TRIGGER, 'INSERT', false);
            $this->createSqliteTrigger(self::UPDATE_TRIGGER, 'UPDATE', true);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->createMySqlTrigger(self::INSERT_TRIGGER, 'INSERT', false);
            $this->createMySqlTrigger(self::UPDATE_TRIGGER, 'UPDATE', true);

            return;
        }

        throw new RuntimeException('Les cours communs ne prennent en charge que MySQL, MariaDB et SQLite.');
    }

    private function createSqliteTrigger(string $name, string $event, bool $isUpdate): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        $condition = $this->teacherConflictCondition($isUpdate);

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE {$event} ON timetable_entries ".
            "FOR EACH ROW WHEN {$condition} BEGIN ".
            "SELECT RAISE(ABORT, 'timetable_entries_teacher_slot_conflict'); END",
        );
    }

    private function createMySqlTrigger(string $name, string $event, bool $isUpdate): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        $condition = $this->teacherConflictCondition($isUpdate);

        DB::unprepared(
            "CREATE TRIGGER {$name} BEFORE {$event} ON timetable_entries FOR EACH ROW ".
            "BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' ".
            "SET MESSAGE_TEXT = 'timetable_entries_teacher_slot_conflict'; END IF; END",
        );
    }

    private function teacherConflictCondition(bool $isUpdate): string
    {
        $excludeCurrent = $isUpdate ? 'AND existing.id <> NEW.id' : '';

        return "NEW.teacher_id IS NOT NULL
            AND NEW.timetable_period_id IS NOT NULL
            AND EXISTS (
                SELECT 1
                FROM timetable_entries AS existing
                WHERE existing.teacher_id = NEW.teacher_id
                  AND existing.day_of_week = NEW.day_of_week
                  AND existing.timetable_period_id = NEW.timetable_period_id
                  {$excludeCurrent}
                  AND (
                      NEW.synchronization_group IS NULL
                      OR existing.synchronization_group IS NULL
                      OR existing.synchronization_group <> NEW.synchronization_group
                  )
            )";
    }

    private function dropTeacherConflictTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::INSERT_TRIGGER);
        DB::unprepared('DROP TRIGGER IF EXISTS '.self::UPDATE_TRIGGER);
    }
};
