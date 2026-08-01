<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Expense;
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
        ClassSubject::query()->create([
            'school_class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
            'is_active' => true,
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
        $paymentResponse = $this->actingAs($admin)
            ->from(route('teacher-fees.show', $statement))
            ->put(route('teacher-fees.pay', $statement), [
                'paid_at' => now()->toDateString(),
                'payment_method' => 'Virement',
                'payment_reference' => 'VIR-2026-001',
            ]);
        $paymentResponse
            ->assertSessionHasNoErrors()
            ->assertSessionHas('teacher_fee_paid', true);
        $this->assertSame('paid', $statement->refresh()->status);
        $this->assertSame('VIR-2026-001', $statement->payment_reference);
        $this->assertDatabaseHas('expenses', [
            'teacher_fee_statement_id' => $statement->id,
            'academic_year_id' => $academicYear->id,
            'category' => 'salaries',
            'beneficiary' => $teacher->name,
            'payment_method' => 'bank_transfer',
            'amount' => 44000,
            'status' => 'valid',
            'created_by' => $admin->id,
        ]);
        $this->assertSame('44000.00', Expense::query()->firstOrFail()->amount);
        $expense = Expense::query()->firstOrFail();
        $this->followRedirects($paymentResponse)
            ->assertOk()
            ->assertSee('id="teacher-fee-paid-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertSee('Dossier professeur')
            ->assertSee(route('teacher-fees.pdf', $statement), false)
            ->assertSee(route('accounting.expenses.show', $expense), false);
        $this->actingAs($admin)
            ->put(route('accounting.expenses.cancel', $expense), [
                'cancellation_reason' => 'Tentative de désynchronisation.',
            ])
            ->assertStatus(422);
        $this->assertSame('valid', $expense->refresh()->status);

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

    public function test_work_sessions_require_assignment_and_reject_duplicates_and_overlaps(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-session-admin');
        $teacher = $this->userWithRole('enseignant', 'teacher-session');
        [$academicYear, $firstClass, $subject] = $this->academicContext();
        $sessionDate = $academicYear->starts_at->copy()->addMonth()->toDateString();
        $payload = [
            'teacher_id' => $teacher->id,
            'school_class_id' => $firstClass->id,
            'subject_id' => $subject->id,
            'session_date' => $sessionDate,
            'starts_at' => '08:00',
            'ends_at' => '10:00',
            'hours_worked' => 2,
            'status' => 'validated',
        ];

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), $payload)
            ->assertSessionHasErrors('subject_id');

        ClassSubject::query()->create([
            'school_class_id' => $firstClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), $payload)
            ->assertSessionHasErrors('starts_at');

        $secondClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $firstClass->level_id,
            'name' => '4e A',
            'status' => 'active',
        ]);
        ClassSubject::query()->create([
            'school_class_id' => $secondClass->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'coefficient' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), [
                ...$payload,
                'school_class_id' => $secondClass->id,
                'starts_at' => '09:00',
                'ends_at' => '11:00',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), [
                ...$payload,
                'school_class_id' => $secondClass->id,
                'session_date' => $academicYear->starts_at->copy()->addMonth()->addDay()->toDateString(),
                'starts_at' => '10:00',
                'ends_at' => '13:00',
                'hours_worked' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('teacher_work_sessions', 2);
        $this->assertSame(5.0, (float) TeacherWorkSession::query()->sum('hours_worked'));
    }

    public function test_teacher_work_session_forms_use_dialogs_and_reopen_after_validation_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-dialog-admin');

        $this->actingAs($admin)
            ->get(route('teacher-work-sessions.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="teacher-work-session-form-dialog"', false)
            ->assertSee('id="teacher-work-session-action-dialog"', false);

        $response = $this->actingAs($admin)
            ->from(route('teacher-work-sessions.index'))
            ->post(route('teacher-work-sessions.store'), []);

        $response
            ->assertRedirect(route('teacher-work-sessions.index'))
            ->assertSessionHasErrors(['teacher_id', 'school_class_id', 'subject_id'])
            ->assertSessionHas('teacher_work_session_open', true);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="teacher-work-session-form-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false);
    }

    public function test_work_session_date_is_limited_to_the_active_academic_year_with_a_clear_message(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-date-admin');
        $teacher = $this->userWithRole('enseignant', 'teacher-date');
        [$academicYear, $schoolClass, $subject] = $this->academicContext();
        $this->travelTo($academicYear->starts_at->copy()->subMonths(2));

        $this->actingAs($admin)
            ->get(route('teacher-work-sessions.index'))
            ->assertOk()
            ->assertSee('value="'.$academicYear->starts_at->toDateString().'"', false)
            ->assertSee('min="'.$academicYear->starts_at->toDateString().'"', false)
            ->assertSee('max="'.$academicYear->ends_at->toDateString().'"', false)
            ->assertSee('Dates autorisées : du '.$academicYear->starts_at->format('d/m/Y').' au '.$academicYear->ends_at->format('d/m/Y').'.');

        $this->actingAs($admin)
            ->post(route('teacher-work-sessions.store'), [
                'teacher_id' => $teacher->id,
                'school_class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'session_date' => $academicYear->starts_at->copy()->subDay()->toDateString(),
                'starts_at' => '07:00',
                'ends_at' => '09:00',
                'hours_worked' => 2,
                'status' => 'validated',
            ])
            ->assertSessionHasErrors([
                'session_date' => 'La date du cours doit être comprise dans l’année scolaire '.$academicYear->name.', à partir du '.$academicYear->starts_at->format('d/m/Y').'.',
            ]);
    }

    public function test_invalid_teacher_fee_payment_reopens_the_payment_dialog(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->userWithRole('admin', 'teacher-payment-dialog-admin');
        $teacher = $this->userWithRole('enseignant', 'teacher-payment-dialog');
        [$academicYear] = $this->academicContext();
        $statement = TeacherFeeStatement::query()->create([
            'reference' => 'HON-DIALOG-001',
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $teacher->id,
            'period_month' => $academicYear->starts_at->copy()->startOfMonth(),
            'beneficiary_name' => $teacher->name,
            'gross_amount' => 50000,
            'withholding_tax_rate' => 2,
            'withholding_tax_amount' => 1000,
            'advance_amount' => 0,
            'other_deduction_amount' => 0,
            'net_amount' => 49000,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('teacher-fees.show', $statement))
            ->put(route('teacher-fees.pay', $statement), [
                'paid_at' => '',
                'payment_method' => 'Inconnu',
            ]);

        $response
            ->assertRedirect(route('teacher-fees.show', $statement))
            ->assertSessionHasErrors(['paid_at', 'payment_method'])
            ->assertSessionHas('teacher_fee_payment_open', true);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="teacher-fee-payment-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertSee('Net à payer');
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
