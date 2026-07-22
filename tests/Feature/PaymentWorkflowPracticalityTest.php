<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWorkflowPracticalityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_file_has_quick_payment_action_for_comptable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$student] = $this->paymentScenario();

        $this->actingAs($user)
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Encaisser')
            ->assertSee(route('payments.create', ['student_id' => $student->id]), false);
    }

    public function test_class_payment_report_can_filter_partial_students(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$partialStudent, $paidStudent, $class] = $this->paymentScenario();

        $this->actingAs($user)
            ->get(route('reports.payment-situation', [
                'school_class_id' => $class->id,
                'status' => 'partial',
            ]))
            ->assertOk()
            ->assertSee($partialStudent->full_name)
            ->assertSee('Partiel')
            ->assertDontSee($paidStudent->full_name);
    }

    public function test_payment_export_contains_line_details_and_cancellation_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$student] = $this->paymentScenario();

        $response = $this->actingAs($user)->get(route('payments.export', ['search' => $student->matricule]));
        $sheetXml = $this->sheetXml($response->getContent());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Montant ligne', $sheetXml);
        $this->assertStringContainsString('Tranche', $sheetXml);
        $this->assertStringContainsString('Motif annulation', $sheetXml);
        $this->assertStringContainsString('Scolarité novembre', $sheetXml);
    }

    public function test_payment_cancellation_requires_reason(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [, , , $payment] = $this->paymentScenario();

        $this->actingAs($user)
            ->from(route('payments.show', $payment))
            ->delete(route('payments.destroy', $payment), ['reason' => ''])
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'valid',
            'cancellation_reason' => null,
        ]);
    }

    private function paymentScenario(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $level = Level::query()->firstOrFail();
        $receiver = $this->userWithRole('comptable');

        $class = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => $level->id,
            'name' => '5e Test Paiements',
            'code' => '5TP',
            'status' => 'active',
        ]);

        $feeType = FeeType::query()->create([
            'name' => 'Scolarité novembre test',
            'code' => 'SCNOV-TEST',
            'is_required' => true,
            'status' => 'active',
        ]);

        $schedule = FeeSchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $class->id,
            'fee_type_id' => $feeType->id,
            'amount' => 25000,
            'period' => 'Novembre',
        ]);

        $partialStudent = Student::query()->create([
            'matricule' => 'LPP-PAY-PARTIAL',
            'first_name' => 'Awa',
            'last_name' => 'Paiement',
            'gender' => 'female',
            'status' => 'active',
        ]);

        $paidStudent = Student::query()->create([
            'matricule' => 'LPP-PAY-PAID',
            'first_name' => 'Issa',
            'last_name' => 'Solde',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $partialEnrollment = Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $partialStudent->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-07-19',
            'type' => 'new',
            'status' => 'active',
        ]);

        $paidEnrollment = Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $paidStudent->id,
            'school_class_id' => $class->id,
            'enrollment_date' => '2026-07-19',
            'type' => 'new',
            'status' => 'active',
        ]);

        $partialPayment = $this->payment($academicYear, $partialStudent, $partialEnrollment, $receiver, 10000);
        PaymentLine::query()->create([
            'payment_id' => $partialPayment->id,
            'fee_type_id' => $feeType->id,
            'fee_schedule_id' => $schedule->id,
            'amount' => 10000,
        ]);

        $paidPayment = $this->payment($academicYear, $paidStudent, $paidEnrollment, $receiver, 25000, 'REC-PAID-TEST');
        PaymentLine::query()->create([
            'payment_id' => $paidPayment->id,
            'fee_type_id' => $feeType->id,
            'fee_schedule_id' => $schedule->id,
            'amount' => 25000,
        ]);

        return [$partialStudent, $paidStudent, $class, $partialPayment];
    }

    private function payment(AcademicYear $academicYear, Student $student, Enrollment $enrollment, User $receiver, int $amount, string $receipt = 'REC-PARTIAL-TEST'): Payment
    {
        return Payment::query()->create([
            'receipt_number' => $receipt,
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'paid_at' => '2026-07-19 09:00:00',
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => 'valid',
            'received_by' => $receiver->id,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-payment-practicality-test-'.uniqid(),
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
