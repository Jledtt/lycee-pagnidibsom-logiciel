<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\PagnidibsomClassSubjectSetupService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClassSubjectPdfImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_then_import_subjects_without_overwriting_existing_assignment_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        app(PagnidibsomClassSubjectSetupService::class)->apply($academicYear);

        $schoolClass = SchoolClass::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('name', '6e')
            ->firstOrFail();
        $french = Subject::query()->where('code', 'FR')->firstOrFail();
        $existingAssignment = ClassSubject::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $french->id)
            ->firstOrFail();
        $existingAssignment->update([
            'teacher_id' => $teacher->id,
            'coefficient' => 7.5,
            'weekly_hours' => 4,
            'is_active' => true,
        ]);

        $pdf = Pdf::loadHTML('<pre>6e
- Français
- Robotique
1ère D
- Hist-Géo
- PC</pre>')->output();

        $this->actingAs($admin)
            ->post(route('subjects.import-pdf.preview'), [
                'subjects_pdf' => UploadedFile::fake()->createWithContent('matieres.pdf', $pdf),
            ])
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHas('subjects.pdf_import_preview', function (array $preview): bool {
                return $preview['summary']['classes'] === 2
                    && $preview['summary']['valid'] === 4
                    && collect($preview['rows'])->contains(
                        fn (array $row): bool => $row['class_name'] === '6e'
                            && $row['subject_name'] === 'Robotique',
                    );
            });

        $this->assertDatabaseMissing('subjects', ['name' => 'Robotique']);

        $this->actingAs($admin)
            ->get(route('subjects.index'))
            ->assertOk()
            ->assertSee('Robotique')
            ->assertSee('Confirmer l’import');

        $this->actingAs($admin)
            ->post(route('subjects.import-pdf.store'))
            ->assertRedirect(route('subjects.index', ['school_class_id' => $schoolClass->id]))
            ->assertSessionHasNoErrors();

        $robotics = Subject::query()->where('name', 'Robotique')->firstOrFail();
        $this->assertDatabaseHas('class_subjects', [
            'school_class_id' => $schoolClass->id,
            'subject_id' => $robotics->id,
            'is_active' => true,
        ]);

        $existingAssignment->refresh();
        $this->assertSame($teacher->id, $existingAssignment->teacher_id);
        $this->assertSame(7.5, (float) $existingAssignment->coefficient);
        $this->assertSame(4.0, (float) $existingAssignment->weekly_hours);
    }

    public function test_pdf_without_class_subject_lists_is_rejected_without_writing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin');
        $before = Subject::query()->count();
        $pdf = Pdf::loadHTML('<p>Document administratif sans liste de matières.</p>')->output();

        $this->actingAs($admin)
            ->post(route('subjects.import-pdf.preview'), [
                'subjects_pdf' => UploadedFile::fake()->createWithContent('illisible.pdf', $pdf),
            ])
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHasErrors('subjects_pdf');

        $this->assertSame($before, Subject::query()->count());
        $this->assertNull(session('subjects.pdf_import_preview'));
    }

    public function test_comptable_cannot_import_subjects_from_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pdf = Pdf::loadHTML('<pre>6e
- Français</pre>')->output();

        $this->actingAs($this->userWithRole('comptable'))
            ->post(route('subjects.import-pdf.preview'), [
                'subjects_pdf' => UploadedFile::fake()->createWithContent('matieres.pdf', $pdf),
            ])
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-subject-pdf-test-'.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
