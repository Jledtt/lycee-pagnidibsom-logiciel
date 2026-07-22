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

class IncompleteStudentDataReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_review_incomplete_student_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithIncompleteStudent();

        $this->actingAs($user)
            ->get(route('reports.incomplete-students', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Données élèves incomplètes')
            ->assertSee($student->full_name)
            ->assertSee('Sexe non renseigné')
            ->assertSee('Date de naissance manquante')
            ->assertSee('Contact parent/tuteur manquant')
            ->assertSee('Photo manquante')
            ->assertSee('Pièce manquante : Acte de naissance')
            ->assertSee('Modifier');
    }

    public function test_filter_can_show_only_students_missing_gender(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithIncompleteStudent();

        $completeStudent = Student::query()->create([
            'matricule' => 'LPP-COMPLETE-001',
            'first_name' => 'Issa',
            'last_name' => 'Kabre',
            'gender' => 'male',
            'birth_date' => '2011-05-20',
            'home_phone' => '70000000',
            'photo_path' => 'students/photos/issa.jpg',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $class->academic_year_id,
            'student_id' => $completeStudent->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-07-19',
            'type' => 'new',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('reports.incomplete-students', [
                'school_class_id' => $class->id,
                'issue' => 'gender',
            ]))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertDontSee($completeStudent->full_name);
    }

    public function test_comptable_cannot_open_incomplete_student_data_report(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $this->actingAs($user)
            ->get(route('reports.incomplete-students'))
            ->assertForbidden();
    }

    public function test_secretariat_can_export_incomplete_student_data_xlsx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithIncompleteStudent();

        $response = $this->actingAs($user)
            ->get(route('reports.incomplete-students.export', ['school_class_id' => $class->id]));
        $sheetXml = $this->sheetXml($response->getContent());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString($student->matricule, $sheetXml);
        $this->assertStringContainsString('À compléter', $sheetXml);
        $this->assertStringContainsString('Sexe non renseigné', $sheetXml);
    }

    private function classWithIncompleteStudent(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5A-INCOMPLETE-DATA',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-INCOMPLETE-001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
            'gender' => null,
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-07-19',
            'type' => 'new',
            'status' => 'active',
        ]);

        return [$class, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-incomplete-data-test',
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
