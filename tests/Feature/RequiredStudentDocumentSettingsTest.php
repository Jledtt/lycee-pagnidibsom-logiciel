<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\RequiredStudentDocument;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredStudentDocumentSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_class_required_document(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('admin');
        [$class] = $this->classWithStudent();

        $this->actingAs($user)
            ->post(route('settings.required-documents.store'), [
                'name' => 'Certificat médical',
                'document_type' => '',
                'scope' => 'class',
                'school_class_id' => $class->id,
                'level_cycle' => '',
                'status' => 'active',
                'position' => 20,
            ])
            ->assertRedirect(route('settings.required-documents.index'));

        $this->assertDatabaseHas('required_student_documents', [
            'name' => 'Certificat médical',
            'document_type' => 'certificat_medical',
            'scope' => 'class',
            'school_class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    public function test_configured_document_is_shown_on_student_file_and_missing_report(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        $secretariat = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithStudent();

        RequiredStudentDocument::query()->create([
            'name' => 'Certificat médical',
            'document_type' => 'certificat_medical',
            'scope' => 'class',
            'school_class_id' => $class->id,
            'status' => 'active',
            'position' => 20,
        ]);

        $this->actingAs($secretariat)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Certificat médical')
            ->assertSee('certificat_medical');

        $this->actingAs($secretariat)
            ->get(route('reports.missing-documents', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Certificat médical');

        $this->actingAs($admin)
            ->put(route('settings.required-documents.update', RequiredStudentDocument::query()->where('document_type', 'certificat_medical')->firstOrFail()), [
                'name' => 'Certificat médical',
                'document_type' => 'certificat_medical',
                'scope' => 'class',
                'school_class_id' => $class->id,
                'level_cycle' => '',
                'status' => 'inactive',
                'position' => 20,
            ])
            ->assertRedirect(route('settings.required-documents.index'));

        $this->actingAs($secretariat)
            ->get(route('reports.missing-documents', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertDontSee('Certificat médical');
    }

    public function test_secretariat_cannot_manage_required_documents(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)
            ->get(route('settings.required-documents.index'))
            ->assertForbidden();
    }

    private function classWithStudent(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '4e A',
            'code' => '4A-DOC-SETTINGS',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-9010',
            'first_name' => 'Issa',
            'last_name' => 'Kabre',
            'gender' => 'male',
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

        return [$class, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-required-documents-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
