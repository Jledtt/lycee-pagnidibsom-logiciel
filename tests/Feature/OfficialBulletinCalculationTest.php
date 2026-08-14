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
use App\Services\ReportCardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialBulletinCalculationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('officialSubjectAverages')]
    public function test_official_subject_average_is_reproduced(
        string $subjectCode,
        float $devoirScore,
        float $compositionScore,
        float $expectedAverage,
    ): void {
        [$student, $schoolClass, $term, $subject, $devoir, $composition] = $this->calculationContext($subjectCode);
        $this->recordGrade($student, $schoolClass, $term, $subject, $devoir, $devoirScore);
        $this->recordGrade($student, $schoolClass, $term, $subject, $composition, $compositionScore);

        $details = app(GradeCalculationService::class)->subjectAverageDetails(
            $student,
            $term,
            $subject->id,
            $schoolClass->id,
        );

        $this->assertSame($devoirScore, $details['devoir']);
        $this->assertSame($compositionScore, $details['composition']);
        $this->assertSame($expectedAverage, $details['general']);
    }

    public static function officialSubjectAverages(): array
    {
        return [
            'Français' => ['FR', 16.50, 11.50, 13.50],
            'Histoire-Géographie' => ['HG', 9.50, 8.00, 8.60],
            'Anglais' => ['ANG', 18.00, 14.50, 15.90],
            'Philosophie' => ['PHILO', 13.25, 12.00, 12.50],
            'Allemand' => ['ALL', 20.00, 17.00, 18.20],
            'Mathématiques' => ['MATH', 10.50, 11.00, 10.80],
            'EPS' => ['EPS', 19.00, 18.00, 18.40],
            'Éducation civique' => ['ECM', 16.00, 16.00, 16.00],
        ];
    }

    public function test_official_totals_and_family_averages_are_reproduced(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = $academicYear->terms()->where('position', 1)->firstOrFail();
        $schoolClass = $this->createClass($academicYear, 'CALC-OFFICIEL');
        $student = $this->createStudent($academicYear, $schoolClass, 'LPP-CALCUL-OFFICIEL');
        $devoir = AssessmentType::query()->where('name', AssessmentType::NAME_DEVOIR)->firstOrFail();
        $composition = AssessmentType::query()->where('name', AssessmentType::NAME_COMPOSITION)->firstOrFail();
        $dataset = [
            'FR' => [16.50, 11.50, 5.0],
            'HG' => [9.50, 8.00, 3.0],
            'ANG' => [18.00, 14.50, 4.0],
            'PHILO' => [13.25, 12.00, 2.0],
            'ALL' => [20.00, 17.00, 3.0],
            'MATH' => [10.50, 11.00, 3.0],
            'EPS' => [19.00, 18.00, 2.0],
            'ECM' => [16.00, 16.00, 2.0],
        ];

        foreach ($dataset as $code => [$devoirScore, $compositionScore, $coefficient]) {
            $subject = Subject::query()->where('code', $code)->firstOrFail();
            ClassSubject::query()->create([
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'coefficient' => $coefficient,
                'is_active' => true,
            ]);
            $this->recordGrade($student, $schoolClass, $term, $subject, $devoir, $devoirScore);
            $this->recordGrade($student, $schoolClass, $term, $subject, $composition, $compositionScore);
        }

        $calculator = app(GradeCalculationService::class);
        $summary = $calculator->termSummary($student, $schoolClass, $term);
        $rowsByCode = $summary['rows']->keyBy(fn (array $row): string => $row['class_subject']->subject->code);

        $this->assertSame(67.50, $rowsByCode['FR']['points']);
        $this->assertSame(63.60, $rowsByCode['ANG']['points']);
        $this->assertSame(54.60, $rowsByCode['ALL']['points']);
        $this->assertSame(36.80, $rowsByCode['EPS']['points']);
        $this->assertSame(24.0, $summary['total_coefficients']);
        $this->assertSame(337.70, $summary['total_points']);
        $this->assertSame(14.07, $summary['general_average']);
        $this->assertSame(13.91, $calculator->familyAverage($rowsByCode->only(['FR', 'HG', 'ANG', 'PHILO', 'ALL'])));
        $this->assertSame(13.84, $calculator->familyAverage($rowsByCode->only(['MATH', 'EPS'])));
        $this->assertSame(16.00, $calculator->familyAverage($rowsByCode->only(['ECM'])));

        app(ReportCardService::class)->generateForClass($schoolClass, $term);
        $this->assertSame(
            14.07,
            (float) ReportCard::query()->where('student_id', $student->id)->firstOrFail()->general_average,
        );
    }

    public function test_annual_average_uses_all_available_terms(): void
    {
        $service = app(ReportCardService::class);

        $this->assertSame(12.76, $service->annualAverage([14.07, 11.99, 12.23]));
        $this->assertSame(13.03, $service->annualAverage([14.07, null, 11.99]));
        $this->assertNull($service->annualAverage([null, null]));
    }

    public function test_annual_summary_reproduces_rank_and_class_statistics(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = $this->createClass($academicYear, 'CALC-ANNUEL');
        $terms = $academicYear->terms()->orderBy('position')->get();
        $datasets = [
            'LPP-ANNUEL-A' => [14.07, 11.99, 12.23],
            'LPP-ANNUEL-B' => [13.22, 13.22, 13.22],
            'LPP-ANNUEL-C' => [9.21, 9.21, 9.21],
        ];

        foreach ($datasets as $matricule => $averages) {
            $student = $this->createStudent($academicYear, $schoolClass, $matricule);

            foreach ($terms as $index => $term) {
                ReportCard::query()->create([
                    'academic_year_id' => $academicYear->id,
                    'term_id' => $term->id,
                    'student_id' => $student->id,
                    'school_class_id' => $schoolClass->id,
                    'general_average' => $averages[$index],
                    'decision' => $term->position === 3 ? 'Passe en classe supérieure' : null,
                    'status' => 'draft',
                ]);
            }
        }

        $summaries = app(ReportCardService::class)->annualSummariesForClass($schoolClass);
        $targetStudent = Student::query()->where('matricule', 'LPP-ANNUEL-A')->firstOrFail();
        $summary = $summaries->get($targetStudent->id);

        $this->assertSame(12.76, $summary['annual_average']);
        $this->assertSame(2, $summary['annual_rank']);
        $this->assertSame(3, $summary['class_size']);
        $this->assertSame(11.73, $summary['annual_class_average']);
        $this->assertSame(13.22, $summary['highest_annual_average']);
        $this->assertSame('Passe en classe supérieure', $summary['decision']);
    }

    public function test_term_position_uses_chronological_order(): void
    {
        $academicYear = AcademicYear::query()->create([
            'name' => '2030-2031',
            'starts_at' => '2030-09-01',
            'ends_at' => '2031-07-31',
            'is_active' => false,
            'status' => 'planned',
        ]);
        $third = $academicYear->terms()->create(['name' => 'Dernier', 'position' => 1, 'starts_at' => '2031-04-01']);
        $first = $academicYear->terms()->create(['name' => 'Premier', 'position' => 3, 'starts_at' => '2030-10-01']);
        $second = $academicYear->terms()->create(['name' => 'Deuxième', 'position' => 2, 'starts_at' => '2031-01-01']);
        $service = app(ReportCardService::class);

        $this->assertSame(1, $service->termPosition($first));
        $this->assertSame(2, $service->termPosition($second));
        $this->assertSame(3, $service->termPosition($third));
    }

    public function test_class_extremes_ignore_unranked_report_cards(): void
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = $academicYear->terms()->where('position', 1)->firstOrFail();
        $schoolClass = $this->createClass($academicYear, 'CALC-EXTREMES');

        foreach ([14.07, 9.38, null] as $index => $average) {
            $student = $this->createStudent($academicYear, $schoolClass, 'LPP-EXTREME-'.$index);
            ReportCard::query()->create([
                'academic_year_id' => $academicYear->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'school_class_id' => $schoolClass->id,
                'general_average' => $average,
                'status' => 'draft',
            ]);
        }

        $this->assertSame(
            ['highest' => 14.07, 'lowest' => 9.38],
            app(ReportCardService::class)->classExtremes($term, $schoolClass),
        );
    }

    private function calculationContext(string $subjectCode): array
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = $academicYear->terms()->where('position', 1)->firstOrFail();
        $subject = Subject::query()->where('code', $subjectCode)->firstOrFail();
        $schoolClass = $this->createClass($academicYear, 'CALC-'.$subjectCode);
        $student = $this->createStudent($academicYear, $schoolClass, 'LPP-CALC-'.$subjectCode);
        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 1,
            'is_active' => true,
        ]);

        return [
            $student,
            $schoolClass,
            $term,
            $subject,
            AssessmentType::query()->where('name', AssessmentType::NAME_DEVOIR)->firstOrFail(),
            AssessmentType::query()->where('name', AssessmentType::NAME_COMPOSITION)->firstOrFail(),
        ];
    }

    private function createClass(AcademicYear $academicYear, string $code): SchoolClass
    {
        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => 'Classe '.$code,
            'code' => $code,
            'status' => 'active',
        ]);
    }

    private function createStudent(AcademicYear $academicYear, SchoolClass $schoolClass, string $matricule): Student
    {
        $student = Student::query()->create([
            'matricule' => $matricule,
            'first_name' => 'Awa',
            'last_name' => 'Kaboré',
            'gender' => 'female',
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

        return $student;
    }

    private function recordGrade(
        Student $student,
        SchoolClass $schoolClass,
        Term $term,
        Subject $subject,
        AssessmentType $type,
        float $score,
    ): void {
        $assessment = Assessment::query()->create([
            'academic_year_id' => $schoolClass->academic_year_id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $type->id,
            'title' => $type->name.' '.$subject->code,
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
        ]);
        Grade::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => $score,
            'status' => Grade::STATUS_GRADED,
            'is_absent' => false,
        ]);
    }
}
