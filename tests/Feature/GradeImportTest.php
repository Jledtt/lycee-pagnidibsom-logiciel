<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class GradeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_download_grade_import_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent();
        $user = $this->userWithRole('enseignant');

        $response = $this->actingAs($user)->get(route('grades.import.template', $assessment));
        $sheetXml = $this->sheetXml($response->getContent());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Matricule', $sheetXml);
        $this->assertStringContainsString($student->matricule, $sheetXml);
    }

    public function test_teacher_can_preview_and_import_grades_csv(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent();
        $user = $this->userWithRole('enseignant');

        $file = UploadedFile::fake()->createWithContent('notes.csv', implode("\n", [
            'Matricule;Nom et prenom;Note;Statut;Commentaire',
            $student->matricule . ';' . $student->full_name . ';15,5;Note saisie;Bon travail',
        ]));

        $this->actingAs($user)
            ->post(route('grades.import.preview', $assessment), ['grades_file' => $file])
            ->assertRedirect(route('grades.import', $assessment));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('grades.import', $assessment))
            ->assertOk()
            ->assertSee('Valide')
            ->assertSee('Bon travail');

        $this->actingAs($user)
            ->post(route('grades.import.store', $assessment))
            ->assertRedirect(route('grades.index', [
                'school_class_id' => $assessment->school_class_id,
                'term_id' => $assessment->term_id,
                'assessment_id' => $assessment->id,
            ]));

        $this->assertDatabaseHas('grades', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 15.5,
            'is_absent' => false,
            'status' => Grade::STATUS_GRADED,
            'comment' => 'Bon travail',
        ]);
    }

    public function test_teacher_can_import_non_counted_grade_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent();
        $user = $this->userWithRole('enseignant');

        $file = UploadedFile::fake()->createWithContent('notes.csv', implode("\n", [
            'Matricule;Note;Statut;Commentaire',
            $student->matricule . ';;Dispense;Certificat medical',
        ]));

        $this->actingAs($user)
            ->post(route('grades.import.preview', $assessment), ['grades_file' => $file])
            ->assertRedirect(route('grades.import', $assessment));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('grades.import', $assessment))
            ->assertOk()
            ->assertSee('Dispense')
            ->assertSee('Valide');

        $this->actingAs($user)
            ->post(route('grades.import.store', $assessment))
            ->assertRedirect();

        $this->assertDatabaseHas('grades', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => null,
            'is_absent' => false,
            'status' => Grade::STATUS_DISPENSED,
            'comment' => 'Certificat medical',
        ]);
    }

    public function test_import_updates_existing_grade(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent();
        $user = $this->userWithRole('enseignant');

        Grade::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 10,
            'is_absent' => false,
        ]);

        $file = UploadedFile::fake()->createWithContent('notes.csv', implode("\n", [
            'Matricule;Note;Absent',
            $student->matricule . ';18;Non',
        ]));

        $this->actingAs($user)
            ->post(route('grades.import.preview', $assessment), ['grades_file' => $file])
            ->assertRedirect(route('grades.import', $assessment));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('grades.import', $assessment))
            ->assertOk()
            ->assertSee('Mise à jour');

        $this->actingAs($user)
            ->post(route('grades.import.store', $assessment))
            ->assertRedirect();

        $this->assertSame('18.00', Grade::query()->where('assessment_id', $assessment->id)->where('student_id', $student->id)->firstOrFail()->score);
    }

    public function test_grade_import_rejects_score_above_assessment_maximum(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent();
        $user = $this->userWithRole('enseignant');

        $file = UploadedFile::fake()->createWithContent('notes.csv', implode("\n", [
            'Matricule;Note;Absent',
            $student->matricule . ';25;Non',
        ]));

        $this->actingAs($user)
            ->post(route('grades.import.preview', $assessment), ['grades_file' => $file])
            ->assertRedirect(route('grades.import', $assessment));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('grades.import', $assessment))
            ->assertOk()
            ->assertSee('Note hors barème');

        $this->actingAs($user)
            ->post(route('grades.import.store', $assessment))
            ->assertRedirect();

        $this->assertDatabaseMissing('grades', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_locked_assessment_cannot_import_grades(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$assessment, $student] = $this->assessmentWithStudent(['is_locked' => true]);
        $user = $this->userWithRole('enseignant');

        $file = UploadedFile::fake()->createWithContent('notes.csv', implode("\n", [
            'Matricule;Note;Absent',
            $student->matricule . ';12;Non',
        ]));

        $this->actingAs($user)
            ->post(route('grades.import.preview', $assessment), ['grades_file' => $file])
            ->assertForbidden();
    }

    private function assessmentWithStudent(array $assessmentOverrides = []): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $subject = Subject::query()->firstOrFail();
        $assessmentType = AssessmentType::query()->where('name', 'Devoir')->firstOrFail();
        $term = $academicYear->terms()->firstOrFail();

        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'code' => '5A',
            'status' => 'active',
        ]);

        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'coefficient' => 2,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'matricule' => 'LPP-2026-0001',
            'first_name' => 'Awa',
            'last_name' => 'Ouedraogo',
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

        $assessment = Assessment::query()->create([
            'academic_year_id' => $academicYear->id,
            'term_id' => $term->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'assessment_type_id' => $assessmentType->id,
            'title' => 'Devoir 1',
            'max_score' => 20,
            'assessment_date' => now()->toDateString(),
            ...$assessmentOverrides,
        ]);

        return [$assessment, $student];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role . '-grade-import-test',
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function sheetXml(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, $content);

        $zip = new \ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($path);

        return $xml;
    }
}
