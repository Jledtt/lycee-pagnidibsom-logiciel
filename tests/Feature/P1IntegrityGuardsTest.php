<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\AcademicYearActivationService;
use App\Services\GuardianAssignmentService;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class P1IntegrityGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_year_guards_reject_multiple_active_years_and_overlaps(): void
    {
        $this->seed(DatabaseSeeder::class);

        $futureYear = AcademicYear::query()->create([
            'name' => '2028-2029',
            'starts_at' => '2028-09-01',
            'ends_at' => '2029-07-31',
            'is_active' => false,
            'status' => 'planned',
        ]);

        $this->assertDatabaseRejects(fn () => $futureYear->update([
            'is_active' => true,
            'status' => 'active',
        ]));
        $this->assertDatabaseRejects(fn () => AcademicYear::query()->create([
            'name' => '2026-2027 bis',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-12-31',
            'is_active' => false,
            'status' => 'planned',
        ]));
        $this->assertDatabaseRejects(fn () => AcademicYear::query()->create([
            'name' => '2030-2031 incoherente',
            'starts_at' => '2030-09-01',
            'ends_at' => '2031-07-31',
            'is_active' => false,
            'status' => 'active',
        ]));

        $this->assertSame(1, AcademicYear::query()->where('is_active', true)->count());
    }

    public function test_academic_year_activation_switches_statuses_atomically(): void
    {
        $this->seed(DatabaseSeeder::class);
        $currentYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $futureYear = AcademicYear::query()->create([
            'name' => '2028-2029',
            'starts_at' => '2028-09-01',
            'ends_at' => '2029-07-31',
            'is_active' => false,
            'status' => 'planned',
        ]);

        app(AcademicYearActivationService::class)->activate($futureYear->id);

        $this->assertDatabaseHas('academic_years', [
            'id' => $currentYear->id,
            'is_active' => false,
            'status' => 'planned',
        ]);
        $this->assertDatabaseHas('academic_years', [
            'id' => $futureYear->id,
            'is_active' => true,
            'status' => 'active',
        ]);
        $this->assertSame(1, AcademicYear::query()->where('is_active', true)->count());
    }

    public function test_guardian_guards_reject_multiple_primary_and_duplicate_relationships(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->student('GUARD');
        $father = $this->guardian('Pere', '70000011');
        $second = $this->guardian('Second', '70000012');

        $student->guardians()->attach($father->id, [
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        $this->assertDatabaseRejects(fn () => $student->guardians()->attach($second->id, [
            'relationship' => 'mother',
            'is_primary' => true,
        ]));
        $this->assertDatabaseRejects(fn () => $student->guardians()->attach($second->id, [
            'relationship' => 'father',
            'is_primary' => false,
        ]));

        $this->assertDatabaseCount('guardian_student', 1);
    }

    public function test_guardian_assignment_keeps_one_primary_and_preserves_replaced_people(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->student('GUARD-SERVICE');
        $service = app(GuardianAssignmentService::class);

        $mother = $service->syncRelationship($student, [
            'mother_first_name' => 'Awa',
            'mother_last_name' => 'Sawadogo',
            'mother_phone_primary' => '70000021',
        ], 'mother', 'mother');

        $this->assertNotNull($mother);
        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $student->id,
            'guardian_id' => $mother->id,
            'relationship' => 'mother',
            'is_primary' => true,
        ]);

        $firstFather = $service->syncRelationship($student, [
            'father_first_name' => 'Paul',
            'father_last_name' => 'Ouedraogo',
            'father_phone_primary' => '70000022',
        ], 'father', 'father');
        $secondFather = $service->syncRelationship($student, [
            'father_first_name' => 'Jean',
            'father_last_name' => 'Kinda',
            'father_phone_primary' => '70000023',
        ], 'father', 'father');

        $this->assertNotNull($firstFather);
        $this->assertNotNull($secondFather);
        $this->assertDatabaseHas('guardians', ['id' => $firstFather->id]);
        $this->assertDatabaseMissing('guardian_student', [
            'student_id' => $student->id,
            'guardian_id' => $firstFather->id,
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $student->id,
            'guardian_id' => $secondFather->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);
        $this->assertSame(1, DB::table('guardian_student')
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->count());
    }

    public function test_timetable_guards_reject_duplicate_cells_teacher_conflicts_and_invalid_times(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $teacher = User::query()->where('username', 'enseignant')->firstOrFail();
        $period = TimetablePeriod::query()->create([
            'academic_year_id' => $academicYear->id,
            'sort_order' => 50,
            'label' => 'Audit 8h-9h',
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'is_break' => false,
            'is_active' => true,
        ]);
        $firstTimetable = $this->timetable($academicYear, 'Contrainte A');
        $secondTimetable = $this->timetable($academicYear, 'Contrainte B');
        $this->entry($firstTimetable, $period, $teacher, 50, 'monday');

        $this->assertDatabaseRejects(fn () => $this->entry(
            $firstTimetable,
            $period,
            null,
            50,
            'monday',
        ));
        $this->assertDatabaseRejects(fn () => $this->entry(
            $secondTimetable,
            $period,
            $teacher,
            51,
            'monday',
        ));
        $this->assertDatabaseRejects(fn () => TimetableEntry::query()->create([
            'timetable_id' => $secondTimetable->id,
            'sort_order' => 52,
            'period_label' => 'Horaire invalide',
            'starts_at' => '10:00',
            'ends_at' => '09:00',
            'day_of_week' => 'tuesday',
            'subject_name' => 'Test',
            'is_break' => false,
            'is_locked' => false,
            'source' => 'manual',
        ]));
    }

    public function test_payment_guards_reject_non_positive_amounts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $receiver = User::query()->where('username', 'admin')->firstOrFail();
        $student = $this->student('PAY');
        $schoolClass = $this->schoolClass($academicYear, 'Paiement garde');
        $enrollment = Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => $academicYear->starts_at,
            'type' => 'new',
            'status' => 'active',
            'created_by' => $receiver->id,
        ]);

        $this->assertDatabaseRejects(fn () => Payment::query()->create([
            'receipt_number' => 'REC-GUARD-ZERO',
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'paid_at' => now(),
            'amount' => 0,
            'payment_method' => 'cash',
            'status' => 'valid',
            'received_by' => $receiver->id,
        ]));

        $payment = Payment::query()->create([
            'receipt_number' => 'REC-GUARD-LINE',
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'paid_at' => now(),
            'amount' => 1000,
            'payment_method' => 'cash',
            'status' => 'valid',
            'received_by' => $receiver->id,
        ]);
        $feeType = FeeType::query()->firstOrFail();

        $this->assertDatabaseRejects(fn () => $payment->lines()->create([
            'fee_type_id' => $feeType->id,
            'amount' => 0,
        ]));
    }

    public function test_integrity_migration_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('academic_years', 'integrity_active_guard'));
        $this->assertTrue(Schema::hasIndex('timetable_entries', 'timetable_entries_cell_unique'));

        $migration = require database_path('migrations/2026_08_14_000000_add_p1_integrity_guards.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('academic_years', 'integrity_active_guard'));
        $this->assertFalse(Schema::hasIndex('timetable_entries', 'timetable_entries_cell_unique'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('academic_years', 'integrity_active_guard'));
        $this->assertTrue(Schema::hasIndex('timetable_entries', 'timetable_entries_cell_unique'));

        $migration->up();

        $this->assertTrue(Schema::hasIndex('guardian_student', 'guardian_student_relationship_unique'));
        $this->assertTrue(Schema::hasIndex('timetable_entries', 'timetable_entries_teacher_slot_unique'));
    }

    private function assertDatabaseRejects(Closure $operation): void
    {
        try {
            $operation();
            $this->fail('La base de données aurait dû refuser cette écriture.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function student(string $suffix): Student
    {
        return Student::query()->create([
            'matricule' => 'LPP-P1-'.$suffix,
            'first_name' => 'Eleve',
            'last_name' => $suffix,
            'gender' => 'male',
            'status' => 'active',
        ]);
    }

    private function guardian(string $name, string $phone): Guardian
    {
        return Guardian::query()->create([
            'first_name' => $name,
            'last_name' => 'Responsable',
            'phone_primary' => $phone,
            'status' => 'active',
        ]);
    }

    private function schoolClass(AcademicYear $academicYear, string $name): SchoolClass
    {
        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function timetable(AcademicYear $academicYear, string $className): Timetable
    {
        return Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $this->schoolClass($academicYear, $className)->id,
            'title' => 'Grille '.$className,
            'status' => 'draft',
        ]);
    }

    private function entry(
        Timetable $timetable,
        TimetablePeriod $period,
        ?User $teacher,
        int $sortOrder,
        string $day,
    ): TimetableEntry {
        return TimetableEntry::query()->create([
            'timetable_id' => $timetable->id,
            'timetable_period_id' => $period->id,
            'sort_order' => $sortOrder,
            'period_label' => $period->label,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'day_of_week' => $day,
            'teacher_id' => $teacher?->id,
            'subject_name' => 'Matiere de test',
            'teacher_name' => $teacher?->name,
            'is_break' => false,
            'is_locked' => false,
            'source' => 'manual',
        ]);
    }
}
