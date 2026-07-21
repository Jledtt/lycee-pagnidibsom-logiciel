<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\MockExam;
use App\Models\MockExamScore;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_prepare_bepc_or_future_bac_mock_exam(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->userWithRole('secretariat');
        $thirdClass = $this->classWithStudent('3e A', 'Troisieme', 'LP-3E-001', 'Awa', 'Ouedraogo');
        $terminalClass = $this->classWithStudent('Terminale A', 'Terminale', 'LP-TA-001', 'Issa', 'Kabre');
        $subject = Subject::query()->firstOrCreate(['name' => 'Francais'], ['code' => 'FR', 'status' => 'active']);

        ClassSubject::query()->create([
            'school_class_id' => $thirdClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 3,
            'is_active' => true,
        ]);
        ClassSubject::query()->create([
            'school_class_id' => $terminalClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('mock-exams.index'))
            ->assertOk()
            ->assertSee('Examens blancs')
            ->assertSee('BAC blanc');

        $this->actingAs($user)
            ->post(route('mock-exams.store'), [
                'name' => 'BAC Blanc N 1',
                'exam_type' => 'bac_blanc',
                'school_class_ids' => [$thirdClass->id, $terminalClass->id],
                'notes' => 'Simulation interne, hors moyenne trimestrielle.',
            ])
            ->assertRedirect();

        $exam = MockExam::query()->where('name', 'BAC Blanc N 1')->firstOrFail();

        $this->assertSame('bac_blanc', $exam->exam_type);
        $this->assertCount(2, $exam->candidates);
        $this->assertDatabaseHas('mock_exam_subjects', [
            'mock_exam_id' => $exam->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($user)
            ->post(route('mock-exams.anonymity.generate', $exam), ['prefix' => 'X'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('mock-exams.rooms.distribute', $exam), ['room_count' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('mock_exam_candidates', [
            'mock_exam_id' => $exam->id,
            'anonymous_code' => 'X1',
            'room_name' => 'Salle 1',
        ]);

        $exam->load(['subjects', 'candidates']);
        $examSubject = $exam->subjects->firstOrFail();

        $this->actingAs($user)
            ->put(route('mock-exams.result-status.update', $exam), [
                'result_status' => 'provisoire',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('mock-exams.subjects.tracking.update', $examSubject), [
                'exam_date' => '2026-07-20',
                'starts_at' => '08:00',
                'ends_at' => '10:00',
                'supervisor_one' => 'Surveillant A',
                'supervisor_two' => 'Surveillant B',
                'expected_copies' => 2,
                'received_copies' => 2,
                'absent_count' => 0,
                'incident_notes' => 'RAS',
                'copies_received_at' => '2026-07-20 10:30:00',
                'copy_receiver_name' => 'Secretariat',
                'correction_teacher_name' => 'Professeur Test',
                'fee_rate' => 500,
                'fee_amount' => 1000,
                'fee_status' => 'approved',
                'fee_paid_at' => null,
                'fee_payment_reference' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mock_exams', [
            'id' => $exam->id,
            'result_status' => 'provisoire',
        ]);

        $this->assertDatabaseHas('mock_exam_subjects', [
            'id' => $examSubject->id,
            'supervisor_one' => 'Surveillant A',
            'received_copies' => 2,
            'fee_amount' => 1000,
        ]);

        foreach ($exam->candidates as $candidate) {
            MockExamScore::query()->create([
                'mock_exam_subject_id' => $examSubject->id,
                'mock_exam_candidate_id' => $candidate->id,
                'score' => 12,
                'is_absent' => false,
            ]);
        }

        $this->actingAs($user)
            ->put(route('mock-exams.jury-decisions.update', $exam), [
                'candidates' => [
                    $exam->candidates->first()->id => [
                        'jury_decision' => 'admitted',
                        'jury_observation' => 'Passe',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mock_exam_candidates', [
            'id' => $exam->candidates->first()->id,
            'jury_decision' => 'admitted',
            'jury_observation' => 'Passe',
        ]);

        $this->actingAs($user)
            ->get(route('print-center.index'))
            ->assertOk()
            ->assertSee('Centre d impression')
            ->assertSee('PV surveillance');

        foreach ([
            route('mock-exams.candidates.pdf', $exam),
            route('mock-exams.rooms.pdf', $exam),
            route('mock-exams.anonymity.pdf', $exam),
            route('mock-exams.surveillance-pv.pdf', $exam),
            route('mock-exams.copy-receipt.pdf', $exam),
            route('mock-exams.results.pdf', [$exam, 'provisoire']),
            route('mock-exams.results.pdf', [$exam, 'definitif']),
            route('mock-exams.jury-decision.pdf', $exam),
            route('mock-exams.teacher-fees.pdf', $exam),
        ] as $pdfUrl) {
            $this->actingAs($user)
                ->get($pdfUrl)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-mock-exam-test-'.uniqid(),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function classWithStudent(string $className, string $levelName, string $matricule, string $firstName, string $lastName): SchoolClass
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => $levelName],
            ['cycle' => 'Secondaire', 'position' => 1],
        );

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => $className,
            'code' => strtoupper(str_replace(' ', '', $className)),
            'capacity' => 60,
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => $matricule,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'male',
            'birth_date' => '2010-01-01',
            'birth_place' => 'Ouagadougou',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => now()->toDateString(),
            'type' => 'new',
            'status' => 'active',
        ]);

        return $schoolClass;
    }
}
