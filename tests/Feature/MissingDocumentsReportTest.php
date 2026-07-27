<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissingDocumentsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_review_missing_documents_by_class(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithStudent();

        StudentDocument::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $class->academic_year_id,
            'name' => 'Photo',
            'document_type' => 'photo',
            'status' => 'received',
            'received_at' => '2026-07-18',
        ]);

        $this->actingAs($user)
            ->get(route('reports.missing-documents', ['school_class_id' => $class->id]))
            ->assertOk()
            ->assertSee('Pièces manquantes')
            ->assertSee($student->full_name)
            ->assertSee('Acte de naissance')
            ->assertSee('Ancien bulletin')
            ->assertSee('Autorisation parentale')
            ->assertDontSee('Photo</span>', false);
    }

    public function test_completed_student_file_is_marked_complete(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithStudent();

        foreach (['birth_certificate', 'photo', 'previous_report_card', 'parent_authorization'] as $type) {
            StudentDocument::query()->create([
                'student_id' => $student->id,
                'academic_year_id' => $class->academic_year_id,
                'name' => $type,
                'document_type' => $type,
                'status' => 'received',
                'received_at' => '2026-07-18',
            ]);
        }

        $this->actingAs($user)
            ->get(route('reports.missing-documents', ['school_class_id' => $class->id, 'status' => 'complete']))
            ->assertOk()
            ->assertSee($student->full_name)
            ->assertSee('Complet')
            ->assertSee('Aucune pièce manquante');
    }

    public function test_comptable_cannot_open_missing_documents_report(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');

        $this->actingAs($user)
            ->get(route('reports.missing-documents'))
            ->assertForbidden();
    }

    public function test_secretariat_can_export_missing_documents_xlsx(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$class, $student] = $this->classWithStudent();

        $response = $this->actingAs($user)
            ->get(route('reports.missing-documents.export', ['school_class_id' => $class->id]));
        $sheetXml = $this->sheetXml($response->getContent());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString($student->matricule, $sheetXml);
        $this->assertStringContainsString('Pièces manquantes', $sheetXml);
        $this->assertStringContainsString('Acte de naissance', $sheetXml);
    }

    private function classWithStudent(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5A-MISSING-DOCUMENTS',
            'status' => 'active',
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-9001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
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

        return [$class, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-missing-documents-test',
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
