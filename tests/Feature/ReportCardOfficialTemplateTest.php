<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\BulletinDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCardOfficialTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_term_contains_no_recall_or_annual_decision(): void
    {
        [$reportCard, $school] = $this->bulletinContext(1, 8, true);
        $reportCard->update([
            'conduct' => 'Très bonne',
            'distinction' => ReportCard::DISTINCTION_HIGH_HONORS_ENCOURAGEMENT,
            'decision' => 'NE DOIT PAS APPARAÎTRE',
        ]);
        $html = $this->renderBulletin($reportCard->fresh(), $school);

        $this->assertStringContainsString('Bulletin du 1er Trimestre', $html);
        $this->assertStringContainsString('Boursier', $html);
        $this->assertStringContainsString('Conduite :</strong> Très bonne', $html);
        $this->assertStringContainsString('Signature', $html);
        $this->assertStringNotContainsString('Visa', $html);
        $this->assertStringNotContainsString('Rappel(s)', $html);
        $this->assertStringNotContainsString("Décision de fin d'année", $html);
        $this->assertStringNotContainsString('NE DOIT PAS APPARAÎTRE', $html);
        $this->assertSame(1, substr_count($html, '>X</span>'));
        $this->assertLessThan(
            strpos($html, 'Bilan Matières littéraires'),
            strpos($html, 'Français'),
        );
        $this->assertStringContainsString('Professeur du bulletin', $html);
        $this->assertStringContainsString('13.50', $html);
    }

    public function test_second_term_only_recalls_the_first_term(): void
    {
        [$reportCard, $school] = $this->bulletinContext(2);
        $html = $this->renderBulletin($reportCard, $school);

        $this->assertStringContainsString('Bulletin du 2ème Trimestre', $html);
        $this->assertStringContainsString('Rappel(s)', $html);
        $this->assertStringContainsString('Moyenne du 1er Trimestre', $html);
        $this->assertStringNotContainsString('Moyenne du 2ème Trimestre', $html);
        $this->assertStringNotContainsString("Décision de fin d'année", $html);
    }

    public function test_third_term_contains_both_recalls_and_the_annual_block(): void
    {
        [$reportCard, $school] = $this->bulletinContext(3);
        $html = $this->renderBulletin($reportCard, $school);

        $this->assertStringContainsString('Bulletin du 3ème Trimestre', $html);
        $this->assertStringContainsString('Moyenne du 1er Trimestre', $html);
        $this->assertStringContainsString('Moyenne du 2ème Trimestre', $html);
        $this->assertStringContainsString("Décision de fin d'année", $html);
        $this->assertStringContainsString('Passe en classe supérieure', $html);
        $this->assertMatchesRegularExpression('/Observation du proviseur\s*:\s*<\/strong>\s*-/', $html);
    }

    public function test_bulletin_stays_on_one_page_with_eight_and_twelve_subjects(): void
    {
        foreach ([8, 12] as $subjectCount) {
            [$reportCard, $school] = $this->bulletinContext(3, $subjectCount);
            $data = app(BulletinDataService::class)->for($reportCard) + ['school' => $school];
            $output = Pdf::loadView('report-cards.pdf', $data)->setPaper('a4')->output();

            if ($qaDirectory = getenv('LPP_PDF_QA_DIR')) {
                if (! is_dir($qaDirectory)) {
                    mkdir($qaDirectory, 0777, true);
                }

                file_put_contents($qaDirectory.DIRECTORY_SEPARATOR."bulletin-{$subjectCount}-matieres.pdf", $output);
            }

            preg_match_all('/\/Type\s*\/Page\b/', $output, $matches);
            $this->assertCount(1, $matches[0], "Le bulletin avec {$subjectCount} matières dépasse une page.");
        }
    }

    /** @return array{ReportCard, SchoolSetting} */
    private function bulletinContext(int $termPosition, int $subjectCount = 8, bool $scholarship = false): array
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $terms = $academicYear->terms()->orderBy('position')->get();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => '2nde A officielle '.$termPosition.'-'.$subjectCount,
            'code' => '2A-OFF-'.$termPosition.'-'.$subjectCount,
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'matricule' => 'LPP-BUL-'.$termPosition.'-'.$subjectCount,
            'first_name' => 'Sompoulkomba Xavière avec un prénom très long',
            'last_name' => 'Kinda',
            'birth_date' => '2010-05-12',
            'birth_place' => 'Ouagadougou',
            'is_scholarship' => $scholarship,
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
        $teacher = User::factory()->create(['name' => 'Professeur du bulletin']);

        foreach (Subject::query()->orderBy('id')->limit($subjectCount)->get() as $index => $subject) {
            ClassSubject::query()->create([
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => $index === 0 ? $teacher->id : null,
                'coefficient' => $index === 1 ? 1.5 : 2,
                'is_active' => true,
            ]);
        }

        foreach ($terms as $index => $term) {
            ReportCard::query()->create([
                'academic_year_id' => $academicYear->id,
                'term_id' => $term->id,
                'student_id' => $student->id,
                'school_class_id' => $schoolClass->id,
                'general_average' => [13.50, 12.25, 14.00][$index] ?? 14.00,
                'rank' => 1,
                'class_size' => 1,
                'class_size_ranked' => 1,
                'class_size_unranked' => 0,
                'appreciation' => 'Bien',
                'decision' => $index === 2 ? 'Passe en classe supérieure' : null,
                'conduct' => 'Bonne',
                'absence_hours' => 3,
                'status' => 'draft',
            ]);
        }

        return [
            ReportCard::query()
                ->where('student_id', $student->id)
                ->where('term_id', $terms[$termPosition - 1]->id)
                ->firstOrFail(),
            SchoolSetting::query()->firstOrFail(),
        ];
    }

    private function renderBulletin(ReportCard $reportCard, SchoolSetting $school): string
    {
        return view('report-cards.pdf', app(BulletinDataService::class)->for($reportCard) + [
            'school' => $school,
        ])->render();
    }
}
