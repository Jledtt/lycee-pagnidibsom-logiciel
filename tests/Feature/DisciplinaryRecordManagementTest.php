<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DisciplinaryRecord;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DisciplinaryRecordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administration_direction_and_surveillant_can_use_discipline_module(): void
    {
        [$record] = $this->disciplineContext();

        foreach (['admin', 'direction', 'surveillant'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('discipline.index'))->assertOk();
            $this->actingAs($user)->get(route('discipline.create'))->assertOk();
            $this->actingAs($user)->get(route('discipline.show', $record))->assertOk();
            $this->actingAs($user)->get(route('discipline.edit', $record))->assertOk();
        }

        foreach (['secretariat', 'comptable', 'enseignant'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('discipline.index'))->assertForbidden();
            $this->actingAs($user)->get(route('discipline.show', $record))->assertForbidden();
            $this->actingAs($user)->put(route('discipline.update', $record), [
                'type' => 'warning',
                'title' => 'Modification interdite',
                'record_date' => '2026-10-12',
            ])->assertForbidden();
        }
    }

    public function test_incident_uses_active_year_and_class_from_active_enrollment(): void
    {
        [, $academicYear, $schoolClass, $student] = $this->disciplineContext();
        $surveillant = $this->userWithRole('surveillant');

        $response = $this->actingAs($surveillant)->post(route('discipline.store'), [
            'student_id' => $student->id,
            'type' => 'warning',
            'title' => 'Retard répété',
            'description' => 'Troisième retard constaté pendant la semaine.',
            'record_date' => '2026-10-15',
            'school_class_id' => 999999,
            'academic_year_id' => 999999,
        ]);

        $created = DisciplinaryRecord::query()->where('title', 'Retard répété')->firstOrFail();

        $response->assertRedirect(route('discipline.show', $created));
        $this->assertSame($academicYear->id, $created->academic_year_id);
        $this->assertSame($schoolClass->id, $created->school_class_id);
        $this->assertSame($surveillant->id, $created->created_by);
        $this->assertSame('active', $created->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'created',
            'auditable_type' => DisciplinaryRecord::class,
            'auditable_id' => (string) $created->id,
            'user_id' => $surveillant->id,
        ]);
    }

    public function test_create_form_uses_the_first_valid_school_day_when_today_is_before_the_active_year(): void
    {
        $this->disciplineContext();

        $this->actingAs($this->userWithRole('surveillant'))
            ->get(route('discipline.create'))
            ->assertOk()
            ->assertSee('value="2026-10-01"', false);
    }

    public function test_discipline_history_is_visible_in_full_student_record_only_with_permission(): void
    {
        [$record, , , $student] = $this->disciplineContext();

        $this->actingAs($this->userWithRole('direction'))
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Suivi disciplinaire')
            ->assertSee($record->title);

        $this->actingAs($this->userWithRole('secretariat'))
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertDontSee('Suivi disciplinaire')
            ->assertDontSee($record->title);
    }

    public function test_student_without_active_enrollment_cannot_receive_an_incident(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->student('DISC-SANS-CLASSE');

        $this->actingAs($this->userWithRole('surveillant'))
            ->post(route('discipline.store'), [
                'student_id' => $student->id,
                'type' => 'observation',
                'title' => 'Incident sans classe',
                'record_date' => '2026-10-15',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('disciplinary_records', ['student_id' => $student->id]);
    }

    public function test_incident_date_must_belong_to_the_active_academic_year(): void
    {
        [, , , $student] = $this->disciplineContext();

        $this->actingAs($this->userWithRole('direction'))
            ->post(route('discipline.store'), [
                'student_id' => $student->id,
                'type' => 'observation',
                'title' => 'Date incohérente',
                'record_date' => '2026-08-14',
            ])
            ->assertSessionHasErrors('record_date');

        $this->assertDatabaseMissing('disciplinary_records', ['title' => 'Date incohérente']);
    }

    public function test_active_incident_can_be_resolved_with_a_recorded_action(): void
    {
        [$record] = $this->disciplineContext();
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->post(route('discipline.resolve', $record), [
                'action_taken' => 'Entretien avec l’élève et convocation du responsable légal.',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertSame('resolved', $record->status);
        $this->assertSame($direction->id, $record->resolved_by);
        $this->assertNotNull($record->resolved_at);
        $this->assertSame('Entretien avec l’élève et convocation du responsable légal.', $record->action_taken);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'updated',
            'auditable_type' => DisciplinaryRecord::class,
            'auditable_id' => (string) $record->id,
            'user_id' => $direction->id,
        ]);

        $this->actingAs($direction)->get(route('discipline.edit', $record))->assertStatus(409);
        $this->actingAs($direction)
            ->post(route('discipline.resolve', $record), ['action_taken' => 'Nouvelle mesure'])
            ->assertSessionHasErrors('status');
    }

    public function test_active_incident_can_be_cancelled_without_deleting_its_history(): void
    {
        [$record] = $this->disciplineContext();
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('discipline.cancel', $record), [
                'cancellation_reason' => 'Incident saisi deux fois après vérification du registre.',
            ])
            ->assertRedirect();

        $record->refresh();

        $this->assertDatabaseHas('disciplinary_records', ['id' => $record->id]);
        $this->assertSame('cancelled', $record->status);
        $this->assertSame($admin->id, $record->cancelled_by);
        $this->assertNotNull($record->cancelled_at);
        $this->assertSame('Incident saisi deux fois après vérification du registre.', $record->cancellation_reason);
        $this->assertFalse(Route::has('discipline.destroy'));
    }

    public function test_resolution_and_cancellation_require_an_explanation(): void
    {
        [$record] = $this->disciplineContext();
        $surveillant = $this->userWithRole('surveillant');

        $this->actingAs($surveillant)
            ->post(route('discipline.resolve', $record), ['action_taken' => ''])
            ->assertSessionHasErrors('action_taken');
        $this->actingAs($surveillant)
            ->post(route('discipline.cancel', $record), ['cancellation_reason' => ''])
            ->assertSessionHasErrors('cancellation_reason');

        $this->assertSame('active', $record->fresh()->status);
    }

    /**
     * @return array{DisciplinaryRecord, AcademicYear, SchoolClass, Student}
     */
    private function disciplineContext(): array
    {
        $this->seed(DatabaseSeeder::class);
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->where('name', '3e')->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '3e Discipline',
            'code' => '3E-DISC',
            'status' => 'active',
        ]);
        $student = $this->student('DISC-01');
        $creator = $this->userWithRole('surveillant');

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => '2026-10-01',
            'type' => 'new',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);

        $record = DisciplinaryRecord::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'type' => 'observation',
            'title' => 'Bavardage répété',
            'description' => 'Faits constatés pendant le cours.',
            'status' => 'active',
            'record_date' => '2026-10-10',
            'created_by' => $creator->id,
        ]);

        return [$record, $academicYear, $schoolClass, $student];
    }

    private function student(string $matricule): Student
    {
        return Student::query()->create([
            'matricule' => $matricule,
            'first_name' => 'Aminata',
            'last_name' => 'Kaboré',
            'gender' => 'female',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-discipline-'.str()->lower(str()->random(6)),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
