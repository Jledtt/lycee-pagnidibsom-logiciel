<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilitySchedule;
use App\Models\Timetable;
use App\Models\TimetableGenerationRun;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\TimetableGenerationService;
use App\Services\TimetableTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class TimetablePlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_preview_and_apply_a_csv_availability_import(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $csv = implode("\n", [
            'Professeur;Jour;Debut;Fin;Statut;Note',
            $teacher->name.';Lundi;07:00;09:45;Disponible;Matinee',
        ]);

        $this->actingAs($user)
            ->post(route('timetables.planning.import.preview'), [
                'availability_file' => UploadedFile::fake()->createWithContent('disponibilites.csv', $csv),
            ])
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['summary']['valid'] === 1
                && $preview['summary']['invalid'] === 0);

        $this->actingAs($user)
            ->post(route('timetables.planning.import.apply'))
            ->assertRedirect(route('timetables.planning'));

        $schedule = TeacherAvailabilitySchedule::query()
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();
        $this->assertSame(TeacherAvailabilitySchedule::STATUS_VALIDATED, $schedule->status);
        $this->assertSame('import', $schedule->source);
        $this->assertSame(3, $schedule->availabilities()->where('status', TeacherAvailability::STATUS_AVAILABLE)->count());
    }

    public function test_import_preview_reports_unknown_teachers_without_writing_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $csv = "Professeur;Jour;Debut;Fin;Statut\nProfesseur Inconnu;Lundi;07:00;09:45;Disponible";

        $this->actingAs($user)
            ->post(route('timetables.planning.import.preview'), [
                'availability_file' => UploadedFile::fake()->createWithContent('invalide.csv', $csv),
            ])
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['summary']['invalid'] === 1);

        $this->actingAs($user)
            ->from(route('timetables.planning'))
            ->post(route('timetables.planning.import.apply'))
            ->assertSessionHasErrors('availability_file');

        $this->assertDatabaseCount('teacher_availability_schedules', 0);
    }

    public function test_word_import_is_reviewed_corrected_then_applied(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $document = $this->docxUpload([
            'Fiche de disponibilités',
            'Professeur : '.$teacher->name,
            'Lundi 07h00 - 09h45 Disponible',
            'Mardi 10h10 à 12h00',
        ]);

        $this->actingAs($user)
            ->post(route('timetables.planning.import.preview'), ['availability_file' => $document])
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['assisted'] === true
                && $preview['summary']['valid'] === 1
                && $preview['summary']['invalid'] === 1);

        $this->actingAs($user)
            ->get(route('timetables.planning.import.review'))
            ->assertOk()
            ->assertSee('Réviser les disponibilités importées')
            ->assertSee($teacher->name)
            ->assertSee('Texte détecté');

        $this->actingAs($user)
            ->patch(route('timetables.planning.import.revise'), [
                'rows' => [
                    [
                        'line' => 2,
                        'selected' => true,
                        'teacher_id' => $teacher->id,
                        'day' => 'monday',
                        'starts_at' => '07:00',
                        'ends_at' => '09:45',
                        'availability_status' => TeacherAvailability::STATUS_AVAILABLE,
                        'note' => 'Matinée',
                    ],
                    [
                        'line' => 3,
                        'selected' => true,
                        'teacher_id' => $teacher->id,
                        'day' => 'tuesday',
                        'starts_at' => '10:10',
                        'ends_at' => '12:00',
                        'availability_status' => TeacherAvailability::STATUS_PREFERRED,
                    ],
                ],
            ])
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['summary']['valid'] === 2
                && $preview['summary']['invalid'] === 0);

        $this->actingAs($user)
            ->post(route('timetables.planning.import.apply'))
            ->assertRedirect(route('timetables.planning'));

        $schedule = TeacherAvailabilitySchedule::query()->where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertSame(3, $schedule->availabilities()->where('status', TeacherAvailability::STATUS_AVAILABLE)->count());
        $this->assertSame(2, $schedule->availabilities()->where('status', TeacherAvailability::STATUS_PREFERRED)->count());
    }

    public function test_text_pdf_is_detected_without_writing_before_confirmation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $binary = Pdf::loadHtml(
            '<p>Professeur : '.e($teacher->name).'</p><p>Mercredi 07h00 - 09h45 Disponible</p>',
        )->output();

        $this->actingAs($user)
            ->post(route('timetables.planning.import.preview'), [
                'availability_file' => UploadedFile::fake()->createWithContent('disponibilites.pdf', $binary),
            ])
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['source_type'] === 'pdf'
                && $preview['summary']['valid'] === 1
                && $preview['summary']['teachers'] === 1);

        $this->assertDatabaseCount('teacher_availability_schedules', 0);
    }

    public function test_pdf_without_readable_text_displays_guidance_instead_of_importing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');

        $this->actingAs($user)
            ->post(route('timetables.planning.import.preview'), [
                'availability_file' => UploadedFile::fake()->createWithContent('scan.pdf', '%PDF-fichier-sans-texte'),
            ])
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['summary']['total'] === 0
                && collect($preview['warnings'])->contains(fn (string $warning): bool => str_contains($warning, 'PDF avec texte')));

        $this->actingAs($user)
            ->get(route('timetables.planning.import.review'))
            ->assertOk()
            ->assertSee('Aucune disponibilité exploitable')
            ->assertSee('PDF avec texte');

        $this->assertDatabaseCount('teacher_availability_schedules', 0);
    }

    public function test_invalid_detected_line_can_be_ignored_before_import(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $csv = implode("\n", [
            'Professeur;Jour;Debut;Fin;Statut;Note',
            $teacher->name.';Lundi;07:00;09:45;Disponible;',
            'Inconnu;Mardi;07:00;09:45;Disponible;',
        ]);

        $this->actingAs($user)->post(route('timetables.planning.import.preview'), [
            'availability_file' => UploadedFile::fake()->createWithContent('mixte.csv', $csv),
        ]);

        $this->actingAs($user)
            ->patch(route('timetables.planning.import.revise'), [
                'rows' => [
                    [
                        'line' => 2,
                        'selected' => true,
                        'teacher_id' => $teacher->id,
                        'day' => 'monday',
                        'starts_at' => '07:00',
                        'ends_at' => '09:45',
                        'availability_status' => TeacherAvailability::STATUS_AVAILABLE,
                    ],
                    [
                        'line' => 3,
                        'selected' => false,
                    ],
                ],
            ])
            ->assertSessionHas('timetables.availability_import_preview', fn (array $preview): bool => $preview['summary']['valid'] === 1
                && $preview['summary']['invalid'] === 0
                && $preview['summary']['ignored'] === 1);

        $this->actingAs($user)->post(route('timetables.planning.import.apply'))->assertRedirect();
        $this->assertDatabaseHas('teacher_availability_schedules', ['teacher_id' => $teacher->id, 'source' => 'import']);
    }

    public function test_import_is_revalidated_if_a_teacher_becomes_inactive_after_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $csv = implode("\n", [
            'Professeur;Jour;Debut;Fin;Statut;Note',
            $teacher->name.';Lundi;07:00;09:45;Disponible;',
        ]);

        $this->actingAs($user)->post(route('timetables.planning.import.preview'), [
            'availability_file' => UploadedFile::fake()->createWithContent('professeur.csv', $csv),
        ]);
        $teacher->update(['status' => 'inactive']);

        $this->actingAs($user)
            ->from(route('timetables.planning.import.review'))
            ->post(route('timetables.planning.import.apply'))
            ->assertRedirect(route('timetables.planning.import.review'))
            ->assertSessionHasErrors('availability_file');

        $this->assertDatabaseCount('teacher_availability_schedules', 0);
    }

    public function test_expired_import_preview_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['timetables.availability_import_preview' => [
                'academic_year_id' => $academicYear->id,
                'expires_at' => now()->subMinute()->timestamp,
            ]])
            ->get(route('timetables.planning.import.review'))
            ->assertRedirect(route('timetables.planning'))
            ->assertSessionHas('warning');
    }

    public function test_generator_creates_a_preview_then_applies_only_a_draft(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = $this->schoolClass('Classe auto');
        $subject = Subject::query()->firstOrCreate(['name' => 'Informatique'], ['code' => 'INFO', 'status' => 'active']);
        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 2,
            'is_active' => true,
        ]);
        $this->validatedAvailability($academicYear, $teacher, $user);
        $this->useStubSolver();

        $this->actingAs($user)
            ->post(route('timetables.planning.generate'))
            ->assertRedirect();

        $run = TimetableGenerationRun::query()->firstOrFail();
        $this->assertTrue($run->canBeApplied());
        $this->assertDatabaseMissing('timetables', ['school_class_id' => $schoolClass->id]);

        $this->actingAs($user)
            ->post(route('timetables.planning.apply', $run))
            ->assertRedirect(route('timetables.planning', ['run' => $run->id]));

        $timetable = Timetable::query()->where('school_class_id', $schoolClass->id)->firstOrFail();
        $this->assertSame('draft', $timetable->status);
        $this->assertSame(2, $timetable->entries()->where('source', 'automatic')->count());
        $this->assertSame(TimetableGenerationRun::STATUS_APPLIED, $run->fresh()->status);
    }

    public function test_generator_refuses_to_apply_a_stale_proposal(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = $this->schoolClass('Classe empreinte');
        $subject = Subject::query()->firstOrCreate(['name' => 'Logique'], ['code' => 'LOG', 'status' => 'active']);
        $assignment = ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 2,
            'is_active' => true,
        ]);
        $this->validatedAvailability($academicYear, $teacher, $user);
        $this->useStubSolver();

        $this->actingAs($user)->post(route('timetables.planning.generate'));
        $run = TimetableGenerationRun::query()->firstOrFail();
        $assignment->update(['weekly_hours' => 3]);

        $this->actingAs($user)
            ->from(route('timetables.planning', ['run' => $run->id]))
            ->post(route('timetables.planning.apply', $run))
            ->assertSessionHasErrors('generation');

        $this->assertDatabaseMissing('timetables', ['school_class_id' => $schoolClass->id]);
    }

    public function test_generator_refuses_to_apply_a_tampered_solution(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = $this->schoolClass('Classe proposition alteree');
        $subject = Subject::query()->create([
            'name' => 'Securite du planning',
            'code' => 'SECP',
            'status' => 'active',
        ]);
        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 1,
            'is_active' => true,
        ]);
        $this->validatedAvailability($academicYear, $teacher, $user);
        $this->useStubSolver();

        $this->actingAs($user)->post(route('timetables.planning.generate'));
        $run = TimetableGenerationRun::query()->firstOrFail();
        $result = $run->result;
        $result['assignments'][0]['period_id'] = 999999;
        $run->update(['result' => $result]);

        $this->actingAs($user)
            ->post(route('timetables.planning.apply', $run->fresh()))
            ->assertSessionHasErrors('generation');

        $this->assertDatabaseMissing('timetables', ['school_class_id' => $schoolClass->id]);
        $this->assertSame(TimetableGenerationRun::STATUS_DRAFT, $run->fresh()->status);
    }

    public function test_generator_never_replaces_an_active_timetable(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $protectedClass = $this->schoolClass('Classe protegee');
        $targetClass = $this->schoolClass('Classe a generer');
        $subject = Subject::query()->firstOrCreate(['name' => 'Reseaux'], ['code' => 'RES', 'status' => 'active']);
        ClassSubject::query()->create([
            'school_class_id' => $targetClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => 1,
            'is_active' => true,
        ]);
        $this->validatedAvailability($academicYear, $teacher, $user);
        $period = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->firstOrFail();
        $protected = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $protectedClass->id,
            'title' => 'Grille officielle a conserver',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $protected->entries()->create([
            'timetable_period_id' => $period->id,
            'sort_order' => $period->sort_order,
            'period_label' => $period->label,
            'starts_at' => $period->starts_at,
            'ends_at' => $period->ends_at,
            'day_of_week' => 'monday',
            'subject_name' => 'Cours officiel',
            'is_break' => false,
            'is_locked' => false,
            'source' => 'manual',
        ]);
        $this->useStubSolver();

        $this->actingAs($user)->post(route('timetables.planning.generate'));
        $run = TimetableGenerationRun::query()->firstOrFail();
        $this->actingAs($user)->post(route('timetables.planning.apply', $run));

        $this->assertDatabaseHas('timetables', [
            'id' => $protected->id,
            'title' => 'Grille officielle a conserver',
            'status' => 'active',
        ]);
        $this->assertSame(1, $protected->entries()->count());
        $this->assertDatabaseHas('timetables', [
            'school_class_id' => $targetClass->id,
            'status' => 'draft',
        ]);
    }

    public function test_generator_rejects_a_solver_result_with_a_teacher_conflict(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $firstClass = $this->schoolClass('Classe conflit solveur A');
        $secondClass = $this->schoolClass('Classe conflit solveur B');

        foreach ([$firstClass, $secondClass] as $index => $schoolClass) {
            $subject = Subject::query()->create([
                'name' => 'Matiere solveur '.($index + 1),
                'code' => 'SOLV'.($index + 1),
                'status' => 'active',
            ]);
            ClassSubject::query()->create([
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'coefficient' => 2,
                'weekly_hours' => 1,
                'is_active' => true,
            ]);
        }

        $this->validatedAvailability($academicYear, $teacher, $user);
        config()->set('services.timetable_solver.python', PHP_BINARY);
        config()->set('services.timetable_solver.script', base_path('tests/Fixtures/timetable_solver_conflict_stub.php'));

        $this->actingAs($user)
            ->post(route('timetables.planning.generate'))
            ->assertRedirect();

        $run = TimetableGenerationRun::query()->firstOrFail();
        $this->assertSame(TimetableGenerationRun::STATUS_FAILED, $run->status);
        $this->assertSame('INVALID_SOLUTION', $run->solver_status);
        $this->assertContains(
            'Le moteur a cree un conflit de professeur sur un meme creneau.',
            $run->diagnostics['blockers'],
        );
        $this->assertDatabaseCount('timetables', 0);
    }

    public function test_readiness_blocks_a_shared_teacher_without_enough_unique_slots(): void
    {
        $this->seed(DatabaseSeeder::class);
        SchoolClass::query()->update(['status' => 'archived']);
        $user = $this->userWithRole('secretariat');
        $teacher = $this->userWithRole('enseignant');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();

        foreach (['A', 'B'] as $suffix) {
            $schoolClass = $this->schoolClass('Classe capacite '.$suffix);
            $subject = Subject::query()->create([
                'name' => 'Matiere capacite '.$suffix,
                'code' => 'CAP'.$suffix,
                'status' => 'active',
            ]);
            ClassSubject::query()->create([
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacher->id,
                'coefficient' => 2,
                'weekly_hours' => 1,
                'is_active' => true,
            ]);
        }

        app(TimetableTemplateService::class)->ensurePeriods($academicYear);
        $period = TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->firstOrFail();
        $schedule = TeacherAvailabilitySchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $teacher->id,
            'status' => TeacherAvailabilitySchedule::STATUS_VALIDATED,
            'source' => 'manual',
            'submitted_at' => now(),
            'validated_at' => now(),
            'updated_by' => $user->id,
        ]);
        $schedule->availabilities()->create([
            'timetable_period_id' => $period->id,
            'day_of_week' => 'monday',
            'status' => TeacherAvailability::STATUS_AVAILABLE,
        ]);

        $readiness = app(TimetableGenerationService::class)->readiness($academicYear);

        $this->assertContains(
            $teacher->name.' : les disponibilites ne couvrent pas son volume horaire total.',
            $readiness['blockers'],
        );
    }

    public function test_non_manager_cannot_access_automatic_planning(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->userWithRole('comptable'))
            ->get(route('timetables.planning'))
            ->assertForbidden();

        $this->actingAs($this->userWithRole('comptable'))
            ->get(route('timetables.planning.import.review'))
            ->assertForbidden();
    }

    private function validatedAvailability(AcademicYear $academicYear, User $teacher, User $actor): void
    {
        app(TimetableTemplateService::class)->ensurePeriods($academicYear);
        $schedule = TeacherAvailabilitySchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $teacher->id,
            'status' => TeacherAvailabilitySchedule::STATUS_VALIDATED,
            'source' => 'manual',
            'submitted_at' => now(),
            'validated_at' => now(),
            'updated_by' => $actor->id,
        ]);
        foreach (TimetablePeriod::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->where('is_break', false)
            ->get() as $period) {
            foreach (array_keys(app(TimetableTemplateService::class)->days()) as $day) {
                $schedule->availabilities()->create([
                    'timetable_period_id' => $period->id,
                    'day_of_week' => $day,
                    'status' => TeacherAvailability::STATUS_AVAILABLE,
                ]);
            }
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-planning-test-'.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function schoolClass(string $name): SchoolClass
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => $name],
            ['cycle' => 'Premier cycle', 'position' => 1],
        );

        return SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)),
            'capacity' => 60,
            'status' => 'active',
        ]);
    }

    private function docxUpload(array $paragraphs): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lpp-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $body = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<w:p><w:r><w:t>'.htmlspecialchars($paragraph, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>')
            ->implode('');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$body
            .'</w:body></w:document>',
        );
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('disponibilites.docx', $contents ?: '');
    }

    private function useStubSolver(): void
    {
        config()->set('services.timetable_solver.python', PHP_BINARY);
        config()->set('services.timetable_solver.script', base_path('tests/Fixtures/timetable_solver_stub.php'));
    }
}
