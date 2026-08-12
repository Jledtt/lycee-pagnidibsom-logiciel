<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\MockExam;
use App\Models\MockExamScore;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class MockExamTranscriptGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_candidate_transcript_is_blocked_by_default(): void
    {
        [$user, $exam, $emptyCandidate] = $this->examWithCandidates();

        $this->actingAs($user)
            ->get(route('mock-exams.candidates.transcript.pdf', [$exam, $emptyCandidate]))
            ->assertRedirect(route('mock-exams.index', [
                'mock_exam_id' => $exam->id,
                'section' => 'candidates',
            ]))
            ->assertSessionHasErrors([
                'transcript' => 'Aucune note saisie pour ce candidat — relevé non généré.',
            ]);
    }

    public function test_empty_candidate_transcript_can_be_forced_with_provisional_banner(): void
    {
        [$user, $exam, $emptyCandidate] = $this->examWithCandidates();
        $response = $this->actingAs($user)
            ->get(route('mock-exams.candidates.transcript.pdf', [
                $exam,
                $emptyCandidate,
                'include_empty' => 1,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'DOCUMENT PROVISOIRE',
            (new Parser)->parseContent($response->getContent())->getText(),
        );
    }

    public function test_collective_transcript_skips_empty_candidates_and_reports_the_count(): void
    {
        [$user, $exam, $emptyCandidate, $scoredCandidate] = $this->examWithCandidates();
        $response = $this->actingAs($user)
            ->get(route('mock-exams.transcripts.pdf', $exam))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Avec Note', $text);
        $this->assertStringNotContainsString('Sans Note', $text);
        $this->assertStringContainsString('1 candidat(s) sans notes non inclus', $text);
    }

    public function test_exam_screen_warns_when_session_year_differs_from_current_year(): void
    {
        [$user, $exam] = $this->examWithCandidates();
        $exam->update([
            'starts_on' => now()->addYear()->startOfYear()->toDateString(),
            'ends_on' => now()->addYear()->endOfYear()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('mock-exams.index', ['mock_exam_id' => $exam->id]))
            ->assertOk()
            ->assertSee('La session '.now()->addYear()->year.' ne correspond pas à l’année en cours '.now()->year.' — vérifie le paramétrage.');
    }

    private function examWithCandidates(): array
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('secretariat');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => 'Troisième garde-fous'],
            ['cycle' => 'Secondaire', 'position' => 3],
        );
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '3e garde-fous',
            'code' => '3E-GARDE-FOUS',
            'status' => 'active',
        ]);
        $subject = Subject::query()->where('status', 'active')->firstOrFail();
        $exam = MockExam::query()->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'BEPC Blanc - garde-fous',
            'exam_type' => 'bepc_blanc',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'status' => 'preparation',
        ]);
        $exam->classes()->attach($schoolClass);
        $examSubject = $exam->subjects()->create([
            'subject_id' => $subject->id,
            'max_score' => 20,
            'coefficient' => 2,
            'position' => 1,
        ]);

        $emptyStudent = Student::query()->create([
            'matricule' => 'MOCK-EMPTY-001',
            'first_name' => 'Sans',
            'last_name' => 'Note',
            'birth_date' => now()->subYears(15)->toDateString(),
            'status' => 'active',
        ]);
        $scoredStudent = Student::query()->create([
            'matricule' => 'MOCK-SCORED-001',
            'first_name' => 'Avec',
            'last_name' => 'Note',
            'birth_date' => now()->subYears(15)->toDateString(),
            'status' => 'active',
        ]);
        $emptyCandidate = $exam->candidates()->create([
            'student_id' => $emptyStudent->id,
            'school_class_id' => $schoolClass->id,
            'status' => 'active',
        ]);
        $scoredCandidate = $exam->candidates()->create([
            'student_id' => $scoredStudent->id,
            'school_class_id' => $schoolClass->id,
            'status' => 'active',
        ]);
        MockExamScore::query()->create([
            'mock_exam_subject_id' => $examSubject->id,
            'mock_exam_candidate_id' => $scoredCandidate->id,
            'score' => 12,
            'is_absent' => false,
        ]);

        return [$user, $exam, $emptyCandidate, $scoredCandidate];
    }
}
