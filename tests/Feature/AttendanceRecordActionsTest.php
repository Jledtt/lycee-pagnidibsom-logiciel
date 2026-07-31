<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_justifies_then_clears_an_attendance_incident(): void
    {
        [$admin, $record] = $this->attendanceContext();

        $this->actingAs($admin)
            ->from(route('attendance.index'))
            ->put(route('attendance.records.justify', $record), [
                'reason' => 'Certificat médical présenté.',
            ])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('excused', $record->status);
        $this->assertSame('Certificat médical présenté.', $record->reason);
        $this->assertSame($admin->id, $record->justified_by);
        $this->assertNotNull($record->justified_at);

        $this->actingAs($admin)
            ->from(route('attendance.index'))
            ->delete(route('attendance.records.clear', $record))
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('present', $record->status);
        $this->assertNull($record->reason);
        $this->assertNull($record->justified_at);
        $this->assertNull($record->justified_by);
    }

    public function test_invalid_justification_reopens_the_incident_dialog(): void
    {
        [$admin, $record] = $this->attendanceContext();

        $response = $this->actingAs($admin)
            ->from(route('attendance.index'))
            ->put(route('attendance.records.justify', $record), ['reason' => '']);

        $response
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHasErrors('reason')
            ->assertSessionHas('attendance_record_open', $record->id);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="attendance-record-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertSee('Enregistrer la justification');
    }

    private function attendanceContext(): array
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create([
            'username' => 'attendance-actions-admin',
            'status' => 'active',
        ]);
        $admin->assignRole('admin');
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '6e Assiduité',
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'matricule' => 'LPP-ATT-ACTION-001',
            'first_name' => 'Aminata',
            'last_name' => 'Assiduite',
            'status' => 'active',
        ]);
        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'student_id' => $student->id,
            'enrollment_date' => $academicYear->starts_at,
            'type' => 'new',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $session = AttendanceSession::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'session_date' => $academicYear->starts_at,
            'created_by' => $admin->id,
        ]);
        $record = AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'reason' => 'Non précisé',
        ]);

        return [$admin, $record];
    }
}
