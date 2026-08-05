<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Services\TimetableGridService;
use App\Services\TimetableTemplateService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_reports_an_incomplete_weekly_volume_and_blocks_publication(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$timetable, $assignment] = $this->timetableWithAssignment('2nde Revision', 2);
        $this->fillFirstCourse($timetable, $assignment);

        $this->actingAs($user)
            ->get(route('timetables.review', $timetable))
            ->assertOk()
            ->assertSee('1/2 creneaux places')
            ->assertSee('Publication impossible pour le moment');

        $this->actingAs($user)
            ->from(route('timetables.review', $timetable))
            ->post(route('timetables.publish', $timetable))
            ->assertRedirect(route('timetables.review', $timetable))
            ->assertSessionHasErrors('publication');

        $this->assertSame('draft', $timetable->fresh()->status);
    }

    public function test_complete_grid_can_be_published_with_traceability_and_locked_courses(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$timetable, $assignment] = $this->timetableWithAssignment('1ere Publication', 1);
        $entry = $this->fillFirstCourse($timetable, $assignment, source: 'automatic');

        $this->actingAs($user)
            ->post(route('timetables.publish', $timetable))
            ->assertRedirect(route('timetables.review', $timetable))
            ->assertSessionHasNoErrors();

        $timetable->refresh();
        $this->assertSame('active', $timetable->status);
        $this->assertSame($user->id, $timetable->published_by);
        $this->assertNotNull($timetable->published_at);
        $this->assertTrue($entry->fresh()->is_locked);

        $this->actingAs($user)
            ->get(route('timetables.review', $timetable))
            ->assertOk()
            ->assertSee('Grille publiée')
            ->assertSee($user->name);
    }

    public function test_active_grid_must_be_reopened_before_unlocking_or_editing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$timetable, $assignment] = $this->timetableWithAssignment('Terminale Protegee', 1);
        $entry = $this->fillFirstCourse($timetable, $assignment);
        $this->actingAs($user)->post(route('timetables.publish', $timetable));

        $this->actingAs($user)
            ->get(route('timetables.edit', $timetable))
            ->assertRedirect(route('timetables.review', $timetable));

        $this->actingAs($user)
            ->patch(route('timetables.entries.lock', [$timetable, $entry]), ['locked' => 0])
            ->assertSessionHasErrors('timetable');

        $this->actingAs($user)
            ->post(route('timetables.reopen', $timetable))
            ->assertRedirect(route('timetables.review', $timetable));

        $this->assertSame('draft', $timetable->fresh()->status);
        $this->assertNull($timetable->fresh()->published_at);
        $this->assertTrue($entry->fresh()->is_locked);

        $this->actingAs($user)
            ->patch(route('timetables.entries.lock', [$timetable, $entry]), ['locked' => 0])
            ->assertSessionHasNoErrors();

        $this->assertFalse($entry->fresh()->is_locked);
    }

    public function test_review_detects_a_room_already_used_by_an_active_class(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('secretariat');
        [$activeTimetable, $activeAssignment] = $this->timetableWithAssignment('3e Salle A', 1);
        $this->fillFirstCourse($activeTimetable, $activeAssignment, room: 'Laboratoire 1');
        $activeTimetable->update(['status' => 'active']);

        [$draftTimetable, $draftAssignment] = $this->timetableWithAssignment('3e Salle B', 1);
        $this->fillFirstCourse($draftTimetable, $draftAssignment, room: 'laboratoire 1');

        $this->actingAs($user)
            ->get(route('timetables.review', $draftTimetable))
            ->assertOk()
            ->assertSee('La salle laboratoire 1 est deja occupee')
            ->assertSee('3e Salle A');
    }

    public function test_user_without_timetable_permission_cannot_open_review(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$timetable] = $this->timetableWithAssignment('Classe Interdite', 1);

        $this->actingAs($user)
            ->get(route('timetables.review', $timetable))
            ->assertForbidden();
    }

    public function test_locked_automatic_course_is_preserved_and_an_unlocked_correction_becomes_manual(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$timetable, $assignment] = $this->timetableWithAssignment('4e Tracabilite', 1);
        $entry = $this->fillFirstCourse($timetable, $assignment, source: 'automatic');
        $entry->update(['is_locked' => true]);

        app(TimetableGridService::class)->update($timetable, [
            'title' => $timetable->title,
            'status' => 'draft',
        ], [$this->entryPayload($entry, 'Valeur malveillante')]);

        $preserved = $timetable->entries()->firstOrFail();
        $this->assertSame($assignment->subject->name, $preserved->subject_name);
        $this->assertSame('automatic', $preserved->source);
        $this->assertTrue($preserved->is_locked);

        $preserved->update(['is_locked' => false]);
        $payload = $this->entryPayload($preserved, 'Activite corrigee');
        $payload['class_subject_id'] = null;
        app(TimetableGridService::class)->update($timetable->fresh(), [
            'title' => $timetable->title,
            'status' => 'draft',
        ], [$payload]);

        $corrected = $timetable->entries()->firstOrFail();
        $this->assertSame('Activite corrigee', $corrected->subject_name);
        $this->assertSame('manual', $corrected->source);
        $this->assertFalse($corrected->is_locked);
    }

    private function timetableWithAssignment(string $className, int $weeklyHours): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrCreate(
            ['name' => $className],
            ['cycle' => 'Second cycle', 'position' => 1],
        );
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => $className,
            'code' => str($className)->slug('-')->upper(),
            'capacity' => 60,
            'status' => 'active',
        ]);
        $teacher = $this->userWithRole('enseignant');
        $subject = Subject::query()->create([
            'name' => 'Matiere '.$className,
            'code' => 'MAT'.uniqid(),
            'status' => 'active',
        ]);
        $assignment = ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 2,
            'weekly_hours' => $weeklyHours,
            'is_active' => true,
        ]);
        $timetable = Timetable::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'title' => 'Grille '.$className,
            'status' => 'draft',
        ]);
        app(TimetableTemplateService::class)->seedBlankEntries($timetable);

        return [$timetable->fresh('entries'), $assignment];
    }

    private function fillFirstCourse(
        Timetable $timetable,
        ClassSubject $assignment,
        string $source = 'manual',
        ?string $room = null,
    ): TimetableEntry {
        $assignment->loadMissing(['subject', 'teacher']);
        $entry = $timetable->entries()
            ->where('is_break', false)
            ->orderBy('sort_order')
            ->orderBy('day_of_week')
            ->firstOrFail();
        $entry->update([
            'class_subject_id' => $assignment->id,
            'subject_id' => $assignment->subject_id,
            'teacher_id' => $assignment->teacher_id,
            'subject_name' => $assignment->subject->name,
            'teacher_name' => $assignment->teacher->name,
            'room' => $room,
            'source' => $source,
        ]);

        return $entry->fresh();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-review-'.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function entryPayload(TimetableEntry $entry, string $subjectName): array
    {
        return [
            'entry_id' => $entry->id,
            'timetable_period_id' => $entry->timetable_period_id,
            'sort_order' => $entry->sort_order,
            'period_label' => $entry->period_label,
            'starts_at' => $entry->starts_at ? substr((string) $entry->starts_at, 0, 5) : null,
            'ends_at' => $entry->ends_at ? substr((string) $entry->ends_at, 0, 5) : null,
            'day_of_week' => $entry->day_of_week,
            'class_subject_id' => $entry->class_subject_id,
            'subject_name' => $subjectName,
            'teacher_name' => $entry->teacher_name,
            'room' => $entry->room,
            'is_break' => 0,
        ];
    }
}
