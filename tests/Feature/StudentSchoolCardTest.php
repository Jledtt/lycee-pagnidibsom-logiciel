<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSchoolCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_generate_student_school_card_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $student = $this->enrolledStudent();

        $response = $this->actingAs($user)->get(route('students.school-card.pdf', $student));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_comptable_cannot_generate_student_school_card_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        $student = $this->enrolledStudent();

        $this->actingAs($user)
            ->get(route('students.school-card.pdf', $student))
            ->assertForbidden();
    }

    private function enrolledStudent(): Student
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '3e A',
            'code' => '3A-CARD',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-9100',
            'first_name' => 'Ousmane',
            'last_name' => 'Nana',
            'gender' => 'male',
            'birth_date' => '2012-12-31',
            'birth_place' => 'Ouagadougou',
            'emergency_contact_phone' => '76723428',
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

        return $student;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-student-card-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
