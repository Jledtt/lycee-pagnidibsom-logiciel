<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Level;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradeCalculationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $academicYear;

    private SchoolClass $schoolClass;

    private Term $term;

    private Subject $subject;

    private Student $student;

    private AssessmentType $devoir;

    private AssessmentType $composition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $this->term = Term::query()
            ->where('academic_year_id', $this->academicYear->id)
            ->where('position', 1)
            ->firstOrFail();
        $this->subject = Subject::query()->where('name', 'Français')->firstOrFail();
        $this->devoir = AssessmentType::query()->where('name', 'Devoir')->firstOrFail();
        $this->composition = AssessmentType::query()->where('name', 'Composition')->firstOrFail();

        $this->schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'level_id' => Level::query()->where('name', 'Terminale')->firstOrFail()->id,
            'name' => 'Classe calcul des moyennes',
            'code' => 'CALC-MOY',
            'status' => 'active',
        ]);

        ClassSubject::query()->create([
            'school_class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'coefficient' => 2,
            'is_active' => true,
        ]);

        $this->student = Student::query()->create([
            'matricule' => 'LPP-CALCUL-001',
            'first_name' => 'Awa',
            'last_name' => 'Kaboré',
            'gender' => 'female',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'student_id' => $this->student->id,
            'school_class_id' => $this->schoolClass->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
            'type' => 'new',
        ]);
    }

    public function test_devoirs_alone_are_renormalized_on_the_present_weight(): void
    {
        $this->grade($this->devoir, 12);
        $this->grade($this->devoir, 16);

        $this->assertSame(14.0, $this->average());
    }

    public function test_composition_alone_is_renormalized_on_the_present_weight(): void
    {
        $this->grade($this->composition, 8);

        $this->assertSame(8.0, $this->average());
    }

    public function test_devoir_and_composition_use_the_40_60_weighting(): void
    {
        $this->grade($this->devoir, 15);
        $this->grade($this->composition, 8);

        $this->assertSame(10.8, $this->average());
    }

    public function test_scores_are_normalized_to_twenty_before_weighting(): void
    {
        $this->grade($this->devoir, 7.5, 10);
        $this->grade($this->composition, 8, 16);

        // 7,5/10 = 15/20 et 8/16 = 10/20, puis pondération 40/60.
        $this->assertSame(12.0, $this->average());
    }

    public function test_inactive_and_zero_weight_types_are_excluded(): void
    {
        $zeroWeight = AssessmentType::query()->create([
            'name' => 'Type sans poids',
            'weight' => 0,
            'status' => 'active',
        ]);
        $inactive = AssessmentType::query()->create([
            'name' => 'Type inactif',
            'weight' => 100,
            'status' => 'inactive',
        ]);

        $this->grade($this->devoir, 14);
        $this->grade($zeroWeight, 20);
        $this->grade($inactive, 20);

        $this->assertSame(14.0, $this->average());
    }

    public function test_no_counted_grade_returns_null(): void
    {
        $this->grade($this->devoir, null, 20, Grade::STATUS_ABSENT);

        $this->assertNull($this->average());
    }

    public function test_disabled_flag_restores_the_simple_average(): void
    {
        config()->set('lpp.grades.weighted_averages', false);

        $this->grade($this->devoir, 15);
        $this->grade($this->composition, 8);

        $this->assertSame(11.5, $this->average());
    }

    public function test_regeneration_command_can_preview_then_apply_the_difference(): void
    {
        $this->grade($this->devoir, 15);
        $this->grade($this->composition, 8);

        ReportCard::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'student_id' => $this->student->id,
            'school_class_id' => $this->schoolClass->id,
            'general_average' => 11.5,
            'rank' => 1,
            'class_size' => 1,
            'status' => 'draft',
        ]);

        $this->artisan('lpp:regenerate-report-cards', [
            '--class' => (string) $this->schoolClass->id,
            '--term' => (string) $this->term->id,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Simulation terminée')
            ->assertSuccessful();

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student->id,
            'general_average' => 11.5,
        ]);

        $this->artisan('lpp:regenerate-report-cards', [
            '--class' => (string) $this->schoolClass->id,
            '--term' => (string) $this->term->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('report_cards', [
            'student_id' => $this->student->id,
            'general_average' => 10.8,
        ]);
    }

    private function average(): ?float
    {
        return app(GradeCalculationService::class)->subjectAverage(
            $this->student,
            $this->term,
            $this->subject->id,
            $this->schoolClass->id,
        );
    }

    private function grade(AssessmentType $type, ?float $score, float $maxScore = 20, string $status = Grade::STATUS_GRADED): void
    {
        $assessment = Assessment::query()->create([
            'academic_year_id' => $this->academicYear->id,
            'term_id' => $this->term->id,
            'school_class_id' => $this->schoolClass->id,
            'subject_id' => $this->subject->id,
            'assessment_type_id' => $type->id,
            'title' => $type->name.' '.Assessment::query()->count(),
            'max_score' => $maxScore,
            'assessment_date' => now()->toDateString(),
        ]);

        Grade::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $this->student->id,
            'score' => $score,
            'is_absent' => $status === Grade::STATUS_ABSENT,
            'status' => $status,
        ]);
    }
}
