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
use App\Models\User;
use App\Services\ReportCardService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassCouncilTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_class_council_ranking_and_statistics(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class, $term] = $this->classWithGrades();

        app(ReportCardService::class)->generateForClass($class, $term);

        $this->actingAs($user)
            ->get(route('class-council.index', ['school_class_id' => $class->id, 'term_id' => $term->id]))
            ->assertOk()
            ->assertSee('Conseil de classe')
            ->assertSee('Moyenne classe')
            ->assertSee('Awa Ouedraogo')
            ->assertSee('Issa Kabre')
            ->assertSee('11,50');
    }

    public function test_shared_rank_is_persisted_and_displayed_as_ex_aequo(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class, $term] = $this->classWithGrades();
        $secondStudent = Student::query()->where('matricule', 'LPP-COUNCIL-002')->firstOrFail();

        Grade::query()->where('student_id', $secondStudent->id)->update(['score' => 15]);
        app(ReportCardService::class)->generateForClass($class, $term);

        $reportCards = ReportCard::query()->where('school_class_id', $class->id)->get();
        $this->assertSame([1], $reportCards->pluck('rank')->unique()->values()->all());
        $this->assertTrue($reportCards->every(fn (ReportCard $card) => $card->rank_is_tied));

        $this->actingAs($user)
            ->get(route('class-council.index', ['school_class_id' => $class->id, 'term_id' => $term->id]))
            ->assertOk()
            ->assertSee('1e ex æquo');
    }

    public function test_admin_can_generate_council_pv_and_student_transcript_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class, $term] = $this->classWithGrades();

        app(ReportCardService::class)->generateForClass($class, $term);
        $reportCard = ReportCard::query()->where('school_class_id', $class->id)->where('term_id', $term->id)->firstOrFail();

        $pvResponse = $this->actingAs($user)
            ->get(route('class-council.pv-pdf', ['school_class_id' => $class->id, 'term_id' => $term->id]));

        $pvResponse->assertOk();
        $pvResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pvResponse->getContent());

        $transcriptResponse = $this->actingAs($user)
            ->get(route('report-cards.transcript-pdf', $reportCard));

        $transcriptResponse->assertOk();
        $transcriptResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $transcriptResponse->getContent());
    }

    public function test_admin_can_export_report_cards_to_xlsx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class, $term] = $this->classWithGrades();

        app(ReportCardService::class)->generateForClass($class, $term);

        $response = $this->actingAs($user)
            ->get(route('report-cards.class-export', ['school_class_id' => $class->id, 'term_id' => $term->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Awa Ouedraogo', $this->sheetXml($response->getContent()));
    }

    public function test_admin_can_view_and_print_annual_redemptions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class, $term] = $this->classWithGrades();

        app(ReportCardService::class)->generateForClass($class, $term);

        $terms = Term::query()
            ->where('academic_year_id', $class->academic_year_id)
            ->orderBy('position')
            ->get();
        $students = Student::query()
            ->whereIn('matricule', ['LPP-COUNCIL-001', 'LPP-COUNCIL-002'])
            ->get()
            ->keyBy('matricule');

        foreach ($terms as $index => $termItem) {
            ReportCard::query()->updateOrCreate([
                'academic_year_id' => $class->academic_year_id,
                'term_id' => $termItem->id,
                'student_id' => $students->get('LPP-COUNCIL-001')->id,
            ], [
                'school_class_id' => $class->id,
                'general_average' => [9.80, 9.90, 9.95][$index] ?? 9.90,
                'rank' => 1,
                'class_size' => 2,
                'status' => 'validated',
            ]);

            ReportCard::query()->updateOrCreate([
                'academic_year_id' => $class->academic_year_id,
                'term_id' => $termItem->id,
                'student_id' => $students->get('LPP-COUNCIL-002')->id,
            ], [
                'school_class_id' => $class->id,
                'general_average' => [8.20, 8.50, 8.40][$index] ?? 8.40,
                'rank' => 2,
                'class_size' => 2,
                'status' => 'validated',
            ]);
        }

        $this->actingAs($user)
            ->get(route('class-council.annual-redemptions', [
                'school_class_id' => $class->id,
                'threshold' => 9.85,
            ]))
            ->assertOk()
            ->assertSee('Rachats conseil')
            ->assertSee('Awa Ouedraogo')
            ->assertDontSee('Issa Kabre');

        $response = $this->actingAs($user)
            ->get(route('class-council.annual-redemptions-pdf', [
                'school_class_id' => $class->id,
                'threshold' => 9.85,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $thirdTerm = $terms->sortByDesc('position')->firstOrFail();
        $annualReportCard = ReportCard::query()
            ->where('student_id', $students->get('LPP-COUNCIL-001')->id)
            ->where('term_id', $thirdTerm->id)
            ->firstOrFail();

        $bulletinResponse = $this->actingAs($user)
            ->get(route('report-cards.pdf', $annualReportCard));

        $bulletinResponse->assertOk();
        $bulletinResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $bulletinResponse->getContent());
    }

    public function test_council_lock_blocks_teacher_grade_updates_but_admin_can_correct(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('enseignant');
        [$class, $term, $assessment, $firstStudent] = $this->classWithGrades();

        ClassSubject::query()
            ->where('school_class_id', $class->id)
            ->where('subject_id', $assessment->subject_id)
            ->update(['teacher_id' => $teacher->id]);
        $assessment->update(['teacher_id' => $teacher->id]);

        $this->actingAs($admin)
            ->post(route('class-council.lock'), [
                'school_class_id' => $class->id,
                'term_id' => $term->id,
            ])
            ->assertRedirect(route('class-council.index', [
                'school_class_id' => $class->id,
                'term_id' => $term->id,
            ]));

        $this->assertTrue($assessment->fresh()->is_locked);
        $this->assertSame('validated', ReportCard::query()->where('student_id', $firstStudent->id)->firstOrFail()->status);

        $this->actingAs($teacher)
            ->put(route('grades.assessments.grades.update', $assessment), [
                'grades' => [
                    $firstStudent->id => ['score' => 18, 'is_absent' => false],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('grades.assessments.grades.update', $assessment), [
                'grades' => [
                    $firstStudent->id => ['score' => 18, 'is_absent' => false],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('18.00', Grade::query()->where('assessment_id', $assessment->id)->where('student_id', $firstStudent->id)->firstOrFail()->score);
    }

    private function classWithGrades(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $term = Term::query()->where('academic_year_id', $academicYear->id)->orderBy('position')->firstOrFail();
        $level = Level::query()->firstOrFail();
        $subject = Subject::query()->where('name', 'Français')->firstOrFail();
        $assessmentType = AssessmentType::query()->where('name', 'Composition')->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '3e Conseil',
            'code' => '3C-COUNCIL',
            'status' => 'active',
        ]);

        ClassSubject::query()->create([
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'coefficient' => 2,
            'is_active' => true,
        ]);

        $students = collect([
            ['first_name' => 'Awa', 'last_name' => 'Ouedraogo', 'matricule' => 'LPP-COUNCIL-001', 'score' => 15],
            ['first_name' => 'Issa', 'last_name' => 'Kabre', 'matricule' => 'LPP-COUNCIL-002', 'score' => 8],
        ])->map(function (array $data) use ($academicYear, $class) {
            $student = Student::query()->create([
                'matricule' => $data['matricule'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => 'female',
                'status' => 'active',
            ]);

            Enrollment::query()->create([
                'academic_year_id' => $academicYear->id,
                'student_id' => $student->id,
                'school_class_id' => $class->id,
                'enrollment_date' => '2026-07-18',
                'type' => 'new',
                'status' => 'active',
            ]);

            $student->setAttribute('test_score', $data['score']);

            return $student;
        });

        $assessment = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Composition',
            'max_score' => 20,
            'assessment_date' => '2026-12-10',
        ]);

        foreach ($students as $student) {
            Grade::query()->create([
                'assessment_id' => $assessment->id,
                'student_id' => $student->id,
                'score' => $student->getAttribute('test_score'),
                'is_absent' => false,
            ]);
        }

        return [$class, $term, $assessment, $students->first()];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-class-council-test-'.Str::random(5),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function sheetXml(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, $content);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($path);

        return $xml;
    }
}
