<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherFeeStatement;
use App\Models\TeacherProfile;
use App\Models\TeacherWorkSession;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_records_hours_and_calculates_teacher_fees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-admin');
        $teacher = $this->userWithRole('enseignant', 'teacher-one');
        [$academicYear, $schoolClass, $subject] = $this->academicContext();

        TeacherProfile::query()->create([
            'user_id' => $teacher->id,
            'specialty' => $subject->name,
            'identity_document_type' => 'CNIB',
            'identity_document_number' => 'B1234567',
            'default_hourly_rate' => 2500,
            'withholding_tax_rate' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), [
                'teacher_id' => $teacher->id,
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'session_date' => $academicYear->starts_at->copy()->addMonth()->toDateString(),
                'starts_at' => '08:00',
                'ends_at' => '12:00',
                'hours_worked' => 20,
                'status' => 'validated',
                'teacher_signed' => 1,
            ])
            ->assertSessionHasNoErrors();

        $session = TeacherWorkSession::query()->firstOrFail();
        $this->assertSame('validated', $session->status);
        $this->assertNotNull($session->teacher_signed_at);

        $this->actingAs($admin)
            ->post(route('teacher-fees.store'), [
                'teacher_id' => $teacher->id,
                'period_month' => $session->session_date->format('Y-m'),
                'session_ids' => [$session->id],
                'rates' => [$session->id => 2500],
                'withholding_tax_rate' => 2,
                'advance_amount' => 5000,
                'other_deduction_amount' => 0,
            ])
            ->assertSessionHasNoErrors();

        $statement = TeacherFeeStatement::query()->with('lines')->firstOrFail();
        $this->assertSame('50000.00', $statement->gross_amount);
        $this->assertSame('1000.00', $statement->withholding_tax_amount);
        $this->assertSame('44000.00', $statement->net_amount);
        $this->assertCount(1, $statement->lines);
        $this->assertSame($session->id, $statement->lines->first()->teacher_work_session_id);

        $this->actingAs($admin)
            ->put(route('teacher-fees.approve', $statement))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->put(route('teacher-fees.pay', $statement), [
                'paid_at' => now()->toDateString(),
                'payment_method' => 'Virement',
                'payment_reference' => 'VIR-2026-001',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $statement->refresh()->status);
        $this->assertSame('VIR-2026-001', $statement->payment_reference);

        $this->actingAs($admin)
            ->get(route('teacher-fees.pdf', $statement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_validated_work_session_cannot_be_paid_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-admin-duplicate');
        $teacher = $this->userWithRole('enseignant', 'teacher-duplicate');
        [$academicYear, $schoolClass, $subject] = $this->academicContext();
        $session = TeacherWorkSession::query()->create([
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'session_date' => $academicYear->starts_at->copy()->addMonth(),
            'hours_worked' => 4,
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => $admin->id,
            'created_by' => $admin->id,
        ]);
        $payload = [
            'teacher_id' => $teacher->id,
            'period_month' => $session->session_date->format('Y-m'),
            'session_ids' => [$session->id],
            'rates' => [$session->id => 2500],
            'withholding_tax_rate' => 2,
            'advance_amount' => 0,
            'other_deduction_amount' => 0,
        ];

        $this->actingAs($admin)->post(route('teacher-fees.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('teacher-fees.store'), $payload)->assertSessionHasErrors('session_ids');

        $this->assertDatabaseCount('teacher_fee_statements', 1);
        $this->assertDatabaseCount('teacher_fee_lines', 1);
    }

    public function test_teacher_can_only_view_their_own_professor_file_and_fees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = $this->userWithRole('enseignant', 'teacher-self');
        $otherTeacher = $this->userWithRole('enseignant', 'teacher-other');

        $this->actingAs($teacher)->get(route('teachers.index'))->assertOk()->assertSee($teacher->name)->assertDontSee($otherTeacher->name);
        $this->actingAs($teacher)->get(route('teachers.show', $teacher))->assertOk();
        $this->actingAs($teacher)->get(route('teachers.show', $otherTeacher))->assertForbidden();
        $this->actingAs($teacher)->get(route('teacher-work-sessions.index'))->assertOk();
        $this->actingAs($teacher)->post(route('teacher-work-sessions.store'), [])->assertForbidden();
    }

    public function test_administration_updates_teacher_profile_and_protects_documents(): void
    {
        Storage::fake('documents');
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-doc-admin');
        $teacher = $this->userWithRole('enseignant', 'teacher-doc');
        [, $schoolClass, $subject] = $this->academicContext();

        $this->actingAs($admin)
            ->put(route('teachers.profile.update', $teacher), [
                'employee_number' => 'PROF-001',
                'specialty' => 'Français',
                'identity_document_type' => 'CNIB',
                'identity_document_number' => 'B7654321',
                'default_hourly_rate' => 2500,
                'withholding_tax_rate' => 2,
                'payment_method' => 'Mobile Money',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('teachers.assignments.store', $teacher), [
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'coefficient' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('teacher-documents.store', $teacher), [
                'name' => 'Contrat de vacation',
                'document_type' => 'Contrat',
                'document_number' => 'CTR-001',
                'document_file' => UploadedFile::fake()->create('contrat.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teacher_profiles', [
            'user_id' => $teacher->id,
            'employee_number' => 'PROF-001',
            'default_hourly_rate' => 2500,
        ]);
        $this->assertDatabaseHas('class_subjects', [
            'teacher_id' => $teacher->id,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
        ]);
        $document = $teacher->teacherDocuments()->firstOrFail();
        Storage::disk('documents')->assertExists($document->file_path);
        $this->actingAs($teacher)->get(route('teacher-documents.download', $document))->assertOk();
    }

    private function userWithRole(string $role, string $username): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function academicContext(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e A',
            'status' => 'active',
        ]);

        return [$academicYear, $schoolClass, Subject::query()->where('status', 'active')->firstOrFail()];
    }
}
