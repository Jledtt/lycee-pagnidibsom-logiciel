<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\DataIntegrityAuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataIntegrityAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_database_is_safe_for_constraints(): void
    {
        $this->seed(DatabaseSeeder::class);

        $report = app(DataIntegrityAuditService::class)->run();

        $this->assertTrue($report['safe_for_constraints']);
        $this->assertSame(0, $report['blocker_count']);
        $this->assertSame([], collect($report['checks'])
            ->firstWhere('key', 'academic_years.multiple_active')['samples']);

        $this->artisan('lpp:audit-data-integrity')
            ->expectsOutputToContain('Aucune anomalie bloquante')
            ->assertSuccessful();
    }

    public function test_blocking_ambiguities_are_reported_without_modifying_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->dropLegacyAuditGuards(
            ['academic_years_integrity_insert', 'academic_years_integrity_update'],
            [
                'academic_years' => ['academic_years_single_active_unique'],
                'guardian_student' => [
                    'guardian_student_single_primary_unique',
                    'guardian_student_relationship_unique',
                ],
            ],
        );
        $activeYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        AcademicYear::query()->create([
            'name' => 'Année active incohérente',
            'starts_at' => $activeYear->starts_at->copy()->addMonth(),
            'ends_at' => $activeYear->ends_at->copy()->addMonth(),
            'is_active' => true,
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'matricule' => 'LPP-AUDIT-001',
            'first_name' => 'Audit',
            'last_name' => 'Intégrité',
            'gender' => 'male',
            'status' => 'active',
        ]);
        $firstGuardian = Guardian::query()->create([
            'first_name' => 'Premier',
            'last_name' => 'Responsable',
            'phone_primary' => '70 00 00 01',
            'status' => 'active',
        ]);
        $secondGuardian = Guardian::query()->create([
            'first_name' => 'Deuxième',
            'last_name' => 'Responsable',
            'phone_primary' => '70 00 00 02',
            'status' => 'active',
        ]);
        $student->guardians()->attach($firstGuardian->id, [
            'relationship' => 'father',
            'is_primary' => true,
        ]);
        $student->guardians()->attach($secondGuardian->id, [
            'relationship' => 'mother',
            'is_primary' => true,
        ]);

        $report = app(DataIntegrityAuditService::class)->run();
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertFalse($report['safe_for_constraints']);
        $this->assertGreaterThan(0, $checks['academic_years.multiple_active']['count']);
        $this->assertGreaterThan(0, $checks['academic_years.overlaps']['count']);
        $this->assertSame(1, $checks['guardian_student.multiple_primary']['count']);
        $this->assertDatabaseCount('academic_years', 2);
        $this->assertDatabaseCount('guardian_student', 2);

        $this->artisan('lpp:audit-data-integrity')
            ->expectsOutputToContain('Ne lancez aucune migration de contraintes')
            ->assertFailed();
    }

    public function test_duplicate_guardian_phone_is_a_warning_not_an_automatic_merge(): void
    {
        $this->seed(DatabaseSeeder::class);
        Guardian::query()->create([
            'first_name' => 'Awa',
            'last_name' => 'Kaboré',
            'phone_primary' => '+226 70 11 22 33',
            'status' => 'active',
        ]);
        Guardian::query()->create([
            'first_name' => 'Moussa',
            'last_name' => 'Kaboré',
            'phone_primary' => '70-11-22-33',
            'status' => 'active',
        ]);

        $report = app(DataIntegrityAuditService::class)->run();
        $check = collect($report['checks'])->firstWhere('key', 'guardians.duplicate_phone');

        $this->assertTrue($report['safe_for_constraints']);
        $this->assertSame(1, $check['count']);
        $this->assertSame(1, $report['warning_count']);
        $this->assertDatabaseCount('guardians', 2);
    }

    public function test_timetable_conflicts_are_reported(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->dropLegacyAuditGuards(
            ['timetable_entries_times_insert', 'timetable_entries_times_update'],
            [
                'timetable_entries' => [
                    'timetable_entries_cell_unique',
                    'timetable_entries_teacher_slot_unique',
                ],
            ],
        );
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $teacher = User::query()->where('username', 'enseignant')->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => 'Classe audit emploi du temps',
            'status' => 'active',
        ]);
        $timetable = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'title' => 'Grille audit',
            'status' => 'draft',
        ]);
        $period = TimetablePeriod::query()->create([
            'academic_year_id' => $academicYear->id,
            'sort_order' => 40,
            'label' => 'Audit 7h-8h',
            'starts_at' => '07:00',
            'ends_at' => '08:00',
            'is_break' => false,
            'is_active' => true,
        ]);

        foreach (['Francais', 'Mathematiques'] as $subjectName) {
            TimetableEntry::query()->create([
                'timetable_id' => $timetable->id,
                'timetable_period_id' => $period->id,
                'sort_order' => 40,
                'period_label' => $period->label,
                'starts_at' => '07:00',
                'ends_at' => '08:00',
                'day_of_week' => 'monday',
                'teacher_id' => $teacher->id,
                'subject_name' => $subjectName,
                'teacher_name' => $teacher->name,
                'is_break' => false,
                'is_locked' => false,
                'source' => 'manual',
            ]);
        }

        TimetableEntry::query()->create([
            'timetable_id' => $timetable->id,
            'sort_order' => 41,
            'period_label' => 'Horaire invalide',
            'starts_at' => '10:00',
            'ends_at' => '09:00',
            'day_of_week' => 'tuesday',
            'subject_name' => 'Test',
            'is_break' => false,
            'is_locked' => false,
            'source' => 'manual',
        ]);

        $checks = collect(app(DataIntegrityAuditService::class)->run()['checks'])->keyBy('key');

        $this->assertSame(1, $checks['timetable_entries.duplicate_cell']['count']);
        $this->assertSame(1, $checks['timetable_entries.teacher_conflict']['count']);
        $this->assertSame(1, $checks['timetable_entries.invalid_times']['count']);
    }

    public function test_payment_inconsistencies_are_reported(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->dropLegacyAuditGuards([
            'payments_amount_insert',
            'payments_amount_update',
            'payment_lines_amount_insert',
            'payment_lines_amount_update',
        ]);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $receiver = User::query()->where('username', 'admin')->firstOrFail();
        $student = Student::query()->create([
            'matricule' => 'LPP-AUDIT-PAYMENT',
            'first_name' => 'Test',
            'last_name' => 'Paiement',
            'gender' => 'female',
            'status' => 'active',
        ]);
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => 'Classe audit paiement',
            'status' => 'active',
        ]);
        $otherClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => 'Autre classe audit paiement',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => $academicYear->starts_at,
            'type' => 'new',
            'status' => 'active',
            'created_by' => $receiver->id,
        ]);
        $feeType = FeeType::query()->where('code', 'INS')->firstOrFail();
        $otherFeeType = FeeType::query()->where('code', 'SCO')->firstOrFail();
        $wrongSchedule = FeeSchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $otherClass->id,
            'fee_type_id' => $feeType->id,
            'amount' => 1000,
            'period' => 'audit',
        ]);
        $payment = Payment::query()->create([
            'receipt_number' => 'REC-AUDIT-001',
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'paid_at' => now(),
            'amount' => 1000,
            'payment_method' => 'cash',
            'status' => 'cancelled',
            'received_by' => $receiver->id,
        ]);
        PaymentLine::query()->create([
            'payment_id' => $payment->id,
            'fee_type_id' => $otherFeeType->id,
            'fee_schedule_id' => $wrongSchedule->id,
            'amount' => 500,
        ]);
        PaymentLine::query()->create([
            'payment_id' => $payment->id,
            'fee_type_id' => $feeType->id,
            'fee_schedule_id' => null,
            'amount' => 0,
        ]);
        Payment::query()->create([
            'receipt_number' => 'REC-AUDIT-002',
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'enrollment_id' => null,
            'paid_at' => now(),
            'amount' => 0,
            'payment_method' => 'cash',
            'status' => 'valid',
            'received_by' => $receiver->id,
        ]);

        $checks = collect(app(DataIntegrityAuditService::class)->run()['checks'])->keyBy('key');

        $this->assertSame(1, $checks['payments.non_positive']['count']);
        $this->assertSame(1, $checks['payment_lines.non_positive']['count']);
        $this->assertSame(1, $checks['payments.line_sum_mismatch']['count']);
        $this->assertSame(1, $checks['payments.without_enrollment']['count']);
        $this->assertSame(1, $checks['payment_lines.schedule_mismatch']['count']);
        $this->assertSame(1, $checks['payments.cancel_state_mismatch']['count']);
    }

    /**
     * Simule une base antérieure à la migration P1 afin de tester le rapport
     * sur des anomalies que le schéma courant refuse désormais d'enregistrer.
     *
     * @param  array<int, string>  $triggers
     * @param  array<string, array<int, string>>  $indexes
     */
    private function dropLegacyAuditGuards(array $triggers, array $indexes = []): void
    {
        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                if (Schema::hasIndex($table, $index)) {
                    Schema::table($table, fn ($blueprint) => $blueprint->dropUnique($index));
                }
            }
        }
    }
}
