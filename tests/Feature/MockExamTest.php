<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\MockExam;
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

        $this->actingAs($user)
            ->get(route('mock-exams.candidates.pdf', $exam))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
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
