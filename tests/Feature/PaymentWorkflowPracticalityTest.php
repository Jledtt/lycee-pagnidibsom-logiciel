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
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\FrenchAmountInWordsService;
use App\Services\PaymentFinancialProfileService;
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

    public function test_payment_list_exposes_modal_with_full_page_fallback(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        $this->paymentScenario();

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('href="'.route('payments.create').'"', false)
            ->assertSee('data-dialog-open="payment-create-dialog"', false)
            ->assertSee('id="payment-create-modal-form"', false)
            ->assertSee('data-prevent-double-submit', false);
    }

    public function test_payment_creation_sets_follow_up_marker(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$student] = $this->paymentScenario();
        $schedule = FeeSchedule::query()->where('period', 'Novembre')->firstOrFail();

        $response = $this->actingAs($user)->post(route('payments.store'), [
            'student_id' => $student->id,
            'payment_method' => 'cash',
            'paid_at' => '2026-07-20 10:00:00',
            'lines' => [[
                'fee_schedule_id' => $schedule->id,
                'amount' => 5000,
            ]],
        ]);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $response
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('payment_created', true);
    }

    public function test_invalid_payment_reopens_the_modal_with_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        $this->paymentScenario();

        $response = $this->actingAs($user)
            ->from(route('payments.index'))
            ->post(route('payments.store'), [
                'payment_method' => 'cash',
            ]);

        $response
            ->assertRedirect(route('payments.index'))
            ->assertSessionHasErrors(['student_id', 'lines']);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="payment-create-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false);
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

        $response = $this->actingAs($user)
            ->from(route('payments.show', $payment))
            ->delete(route('payments.destroy', $payment), ['reason' => '']);

        $response
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHasErrors('reason');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('id="cancel-payment-dialog"', false)
            ->assertSee('open data-dialog-open-on-load', false)
            ->assertDontSee("confirm('Annuler ce paiement ?')", false);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'valid',
            'cancellation_reason' => null,
        ]);
    }

    public function test_receipt_pdf_lists_schedule_lines_and_clean_totals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->userWithRole('comptable');
        [$student, , , $payment] = $this->paymentScenario();

        $payment->load([
            'student',
            'academicYear',
            'enrollment.schoolClass',
            'receiver',
            'lines.feeType',
            'lines.feeSchedule.feeType',
        ]);

        $profileService = app(PaymentFinancialProfileService::class);
        $receiptLines = $payment->lines->map(fn (PaymentLine $line) => [
            'designation' => ($line->feeType?->name ?? 'Autres frais')
                .($line->feeSchedule?->period ? ' - '.$line->feeSchedule->period : ''),
            'amount' => (float) $line->amount,
        ]);
        $html = view('payments.receipt-pdf', [
            'amountInWords' => app(FrenchAmountInWordsService::class)->convert($payment->amount),
            'methodLabels' => ['cash' => 'Espèces'],
            'payment' => $payment,
            'receiptLines' => $receiptLines,
            'school' => SchoolSetting::query()->first(),
            'summary' => $profileService->studentPaymentSummary($student, $payment->academicYear),
        ])->render();

        $this->assertStringContainsString('Reçu de paiement', $html);
        $this->assertStringContainsString('Désignation du frais payé', $html);
        $this->assertStringContainsString('Total déjà payé', $html);
        $this->assertStringContainsString('Scolarité novembre test', $html);
        $this->assertStringContainsString('Total de ce reçu', $html);
        $this->assertStringContainsString('Dix mille francs CFA', $html);

        $this->actingAs($user)
            ->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=recu-rec-partial-test-awa-paiement.pdf');
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
