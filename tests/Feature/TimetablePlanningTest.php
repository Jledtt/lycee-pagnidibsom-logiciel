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
use App\Services\TimetableTemplateService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

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
            ->assertRedirect(route('timetables.planning'))
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

    public function test_non_manager_cannot_access_automatic_planning(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->actingAs($this->userWithRole('comptable'))
            ->get(route('timetables.planning'))
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

    private function useStubSolver(): void
    {
        config()->set('services.timetable_solver.python', PHP_BINARY);
        config()->set('services.timetable_solver.script', base_path('tests/Fixtures/timetable_solver_stub.php'));
    }
}
