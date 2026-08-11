<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\GradeCalculationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermPeriodGradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_periods_separate_monthly_devoirs_and_feed_the_trimester_average(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('admin');
        [$academicYear, $schoolClass, $term, $subject, $assessmentType, $compositionType, $student] = $this->classWithStudentAndSubject();
        $firstPeriod = $term->periods()->where('position', 1)->firstOrFail();
        $secondPeriod = $term->periods()->where('position', 2)->firstOrFail();

        $this->actingAs($user)
            ->post(route('grades.assessments.store'), [
                'school_class_id' => $schoolClass->id,
                'term_id' => $term->id,
                'term_period_id' => $firstPeriod->id,
                'subject_id' => $subject->id,
                'assessment_type_id' => $assessmentType->id,
                'title' => '1er devoir - Français',
                'max_score' => 20,
                'assessment_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $firstAssessment = Assessment::query()->where('title', '1er devoir - Français')->firstOrFail();

        Grade::query()->create([
            'assessment_id' => $firstAssessment->id,
            'student_id' => $student->id,
            'score' => 10,
            'is_absent' => false,
            'entered_by' => $user->id,
        ]);

        $secondAssessment = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'term_period_id' => $secondPeriod->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => '2e devoir - Français',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $secondAssessment->id,
            'student_id' => $student->id,
            'score' => 18,
            'is_absent' => false,
            'entered_by' => $user->id,
        ]);

        $composition = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $compositionType->id,
            'title' => 'Composition - Français',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $composition->id,
            'student_id' => $student->id,
            'score' => 20,
            'is_absent' => false,
            'entered_by' => $user->id,
        ]);

        $calculator = app(GradeCalculationService::class);

        $this->assertDatabaseHas('assessments', [
            'title' => '1er devoir - Français',
            'term_period_id' => $firstPeriod->id,
        ]);
        $this->assertSame(10.0, $calculator->generalAverage($student, $schoolClass, $term, $firstPeriod->id));
        $this->assertSame(18.0, $calculator->generalAverage($student, $schoolClass, $term, $secondPeriod->id));
        // Moyenne des devoirs 14 × 40 % + composition 20 × 60 %.
        $this->assertSame(17.6, $calculator->generalAverage($student, $schoolClass, $term));

        $interrogation = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'term_period_id' => $firstPeriod->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Interrogation - Français',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $interrogation->id,
            'student_id' => $student->id,
            'score' => 14,
            'is_absent' => false,
            'entered_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('grades.assessments.pdf', $firstAssessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('grades.assessments.register-pdf', $firstAssessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $paperSheetPdf = $this->actingAs($user)
            ->get(route('grades.assessments.paper-sheet-pdf', $firstAssessment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/',
            $paperSheetPdf->getContent(),
            'La fiche papier des notes doit rester en A4 portrait.',
        );

        preg_match_all('/\/Type\s*\/Page\b/', $paperSheetPdf->getContent(), $paperSheetPages);
        $this->assertCount(1, $paperSheetPages[0], 'Une petite classe ne doit pas produire de page PDF blanche.');

        $this->actingAs($user)
            ->get(route('report-cards.period-class-pdf', [
                'school_class_id' => $schoolClass->id,
                'term_id' => $term->id,
                'term_period_id' => $firstPeriod->id,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_counted_grade_statuses_do_not_reduce_average(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('admin');
        [$academicYear, $schoolClass, $term, $subject, $assessmentType, , $student] = $this->classWithStudentAndSubject();

        $counted = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Devoir note',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $counted->id,
            'student_id' => $student->id,
            'score' => 16,
            'is_absent' => false,
            'status' => Grade::STATUS_GRADED,
            'entered_by' => $user->id,
        ]);

        $dispensed = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Devoir dispense',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $dispensed->id,
            'student_id' => $student->id,
            'score' => null,
            'is_absent' => false,
            'status' => Grade::STATUS_DISPENSED,
            'entered_by' => $user->id,
        ]);

        $absent = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Devoir absent',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            'teacher_id' => $user->id,
        ]);

        Grade::query()->create([
            'assessment_id' => $absent->id,
            'student_id' => $student->id,
            'score' => null,
            'is_absent' => true,
            'status' => Grade::STATUS_ABSENT,
            'entered_by' => $user->id,
        ]);

        $calculator = app(GradeCalculationService::class);

        $this->assertSame(16.0, $calculator->generalAverage($student, $schoolClass, $term));
    }

    private function classWithStudentAndSubject(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = Term::query()->where('academic_year_id', $academicYear->id)->where('position', 1)->firstOrFail();
        $level = Level::query()->where('name', 'Terminale')->firstOrFail();
        $subject = Subject::query()->where('name', 'Français')->firstOrFail();
        $assessmentType = AssessmentType::query()->where('name', 'Devoir')->firstOrFail();
        $compositionType = AssessmentType::query()->where('name', 'Composition')->firstOrFail();

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => 'Terminale A',
            'code' => 'TA',
            'status' => 'active',
        ]);

        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 2,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-TERM',
            'first_name' => 'Issa',
            'last_name' => 'Kabre',
            'gender' => 'male',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'type' => 'new',
        ]);

        return [$academicYear, $schoolClass, $term, $subject, $assessmentType, $compositionType, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-term-period-test-'.uniqid(),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
