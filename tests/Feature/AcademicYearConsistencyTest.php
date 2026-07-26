<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AssessmentType;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermPeriod;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\GradeEntryService;
use App\Services\MockExamService;
use App\Services\PaymentService;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicYearConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_rejects_a_class_from_another_academic_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->admin();
        $activeYear = $this->activeYear();
        $otherYear = $this->otherYear();
        $activeClass = $this->schoolClass($activeYear, 'Active enrollment');
        $otherClass = $this->schoolClass($otherYear, 'Other enrollment');
        $student = $this->student('ENROLL');

        $this->actingAs($user)
            ->post(route('enrollments.store'), [
                'student_id' => $student->id,
                'school_class_id' => $otherClass->id,
                'enrollment_date' => now()->toDateString(),
                'type' => 'new',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('school_class_id');

        $this->assertDatabaseMissing('enrollments', [
            'academic_year_id' => $activeYear->id,
            'student_id' => $student->id,
        ]);

        $this->assertValidationFails(
            fn () => app(EnrollmentService::class)->enroll($student, $otherClass, $activeYear),
            'school_class_id',
        );

        $oldEnrollment = Enrollment::query()->create([
            'academic_year_id' => $otherYear->id,
            'student_id' => $student->id,
            'school_class_id' => $otherClass->id,
            'enrollment_date' => now()->toDateString(),
            'type' => 'new',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->put(route('enrollments.update', $oldEnrollment), [
                'school_class_id' => $activeClass->id,
                'enrollment_date' => now()->toDateString(),
                'type' => 'new',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('school_class_id');

        $this->assertSame($otherClass->id, $oldEnrollment->fresh()->school_class_id);
    }

    public function test_timetable_rejects_a_class_from_another_academic_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->admin();
        $otherClass = $this->schoolClass($this->otherYear(), 'Other timetable');

        $this->actingAs($user)
            ->post(route('timetables.store'), [
                'school_class_id' => $otherClass->id,
                'title' => 'Emploi incohérent',
            ])
            ->assertSessionHasErrors('school_class_id');

        $this->actingAs($user)
            ->post(route('timetables.example'), [
                'school_class_id' => $otherClass->id,
            ])
            ->assertSessionHasErrors('school_class_id');

        $this->assertDatabaseMissing('timetables', [
            'school_class_id' => $otherClass->id,
        ]);
    }

    public function test_assessment_rejects_class_and_term_from_another_academic_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->admin();
        $activeYear = $this->activeYear();
        $otherYear = $this->otherYear();
        $otherClass = $this->schoolClass($otherYear, 'Other assessment');
        $otherTerm = $this->term($otherYear);
        $otherPeriod = TermPeriod::query()->create([
            'term_id' => $otherTerm->id,
            'name' => 'Période étrangère',
            'position' => 1,
            'status' => 'active',
        ]);
        $subject = Subject::query()->where('status', 'active')->firstOrFail();
        $assessmentType = AssessmentType::query()->where('status', 'active')->firstOrFail();
        $payload = [
            'school_class_id' => $otherClass->id,
            'term_id' => $otherTerm->id,
            'term_period_id' => $otherPeriod->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Évaluation incohérente',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
        ];

        $this->actingAs($user)
            ->post(route('grades.assessments.store'), $payload)
            ->assertSessionHasErrors(['school_class_id', 'term_id']);

        $this->assertDatabaseMissing('assessments', ['title' => $payload['title']]);

        $this->assertValidationFails(
            fn () => app(GradeEntryService::class)->createAssessment($activeYear, $payload, $user),
            'school_class_id',
        );
    }

    public function test_mock_exam_rejects_classes_and_term_from_another_academic_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->admin();
        $activeYear = $this->activeYear();
        $otherYear = $this->otherYear();
        $otherClass = $this->schoolClass($otherYear, 'Other mock exam');
        $otherTerm = $this->term($otherYear);
        $payload = [
            'name' => 'Examen blanc incohérent',
            'exam_type' => 'bac_blanc',
            'term_id' => $otherTerm->id,
            'school_class_ids' => [$otherClass->id],
        ];

        $this->actingAs($user)
            ->post(route('mock-exams.store'), $payload)
            ->assertSessionHasErrors(['term_id', 'school_class_ids.0']);

        $this->assertDatabaseMissing('mock_exams', ['name' => $payload['name']]);

        $this->assertValidationFails(
            fn () => app(MockExamService::class)->createExam($activeYear, $payload),
            'school_class_ids',
        );
    }

    public function test_payment_rejects_a_fee_schedule_from_another_academic_year(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->admin();
        $activeYear = $this->activeYear();
        $otherYear = $this->otherYear();
        $activeClass = $this->schoolClass($activeYear, 'Active payment');
        $otherClass = $this->schoolClass($otherYear, 'Other payment');
        $student = $this->student('PAYMENT');
        $feeType = FeeType::query()->where('status', 'active')->firstOrFail();
        $otherSchedule = FeeSchedule::query()->create([
            'academic_year_id' => $otherYear->id,
            'school_class_id' => $otherClass->id,
            'fee_type_id' => $feeType->id,
            'amount' => 25000,
            'period' => 'Autre année',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $activeYear->id,
            'student_id' => $student->id,
            'school_class_id' => $activeClass->id,
            'enrollment_date' => now()->toDateString(),
            'type' => 'new',
            'status' => 'active',
        ]);

        $lines = [[
            'fee_type_id' => $feeType->id,
            'fee_schedule_id' => $otherSchedule->id,
            'amount' => 10000,
        ]];

        $this->actingAs($user)
            ->post(route('payments.store'), [
                'student_id' => $student->id,
                'payment_method' => 'cash',
                'paid_at' => now()->toDateString(),
                'lines' => $lines,
            ])
            ->assertSessionHasErrors('lines.0.fee_schedule_id');

        $this->assertDatabaseMissing('payments', [
            'academic_year_id' => $activeYear->id,
            'student_id' => $student->id,
        ]);

        $this->assertValidationFails(
            fn () => app(PaymentService::class)->createPayment($student, $activeYear, $user, $lines),
            'lines.0.fee_schedule_id',
        );
    }

    private function activeYear(): AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->firstOrFail();
    }

    private function otherYear(): AcademicYear
    {
        return AcademicYear::query()->firstOrCreate(
            ['name' => '2027-2028'],
            [
                'starts_at' => '2027-09-01',
                'ends_at' => '2028-06-30',
                'is_active' => false,
                'status' => 'planned',
            ],
        );
    }

    private function schoolClass(AcademicYear $academicYear, string $label): SchoolClass
    {
        $suffix = strtoupper(substr(md5($label), 0, 6));

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => $label,
            'code' => $suffix,
            'capacity' => 60,
            'status' => 'active',
        ]);
    }

    private function term(AcademicYear $academicYear): Term
    {
        return Term::query()->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Trimestre étranger',
            'type' => 'trimestre',
            'position' => 1,
            'is_closed' => false,
        ]);
    }

    private function student(string $suffix): Student
    {
        return Student::query()->create([
            'matricule' => 'LPP-CONSISTENCY-'.$suffix,
            'first_name' => 'Test',
            'last_name' => $suffix,
            'gender' => 'male',
            'status' => 'active',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'username' => 'academic-year-consistency-'.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    private function assertValidationFails(Closure $callback, string $key): void
    {
        try {
            $callback();
            $this->fail("La validation attendue pour {$key} n’a pas été déclenchée.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
