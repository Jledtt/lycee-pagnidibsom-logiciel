<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Level;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentBackdatingTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private FeeSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00', 'Africa/Ouagadougou'));
        $this->seed(DatabaseSeeder::class);
        $this->createPaymentScenario();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_future_payment_date_is_rejected_even_for_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->payload('2026-08-06 09:00:00'))
            ->assertSessionHasErrors('paid_at');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_old_payment_date_is_rejected_without_backdate_permission(): void
    {
        $accountant = $this->userWithRole('comptable');

        $this->assertFalse($accountant->can('payments.backdate'));

        $this->actingAs($accountant)
            ->post(route('payments.store'), $this->payload('2026-08-02 09:00:00'))
            ->assertSessionHasErrors('paid_at');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_accountant_can_use_the_two_day_calendar_window(): void
    {
        $accountant = $this->userWithRole('comptable');

        $this->actingAs($accountant)
            ->post(route('payments.store'), $this->payload('2026-08-03 00:00:00'))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_admin_and_direction_receive_backdate_permission(): void
    {
        $this->assertTrue($this->userWithRole('admin')->can('payments.backdate'));
        $this->assertTrue($this->userWithRole('direction')->can('payments.backdate'));
    }

    public function test_authorized_backdate_is_saved_and_audited(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->payload('2026-07-01 09:00:00'))
            ->assertSessionDoesntHaveErrors();

        $payment = Payment::query()->firstOrFail();
        $activity = ActivityLog::query()->where('action', 'payment.backdated')->firstOrFail();

        $this->assertSame('2026-07-01 09:00:00', $payment->paid_at?->format('Y-m-d H:i:s'));
        $this->assertSame($payment->getMorphClass(), $activity->auditable_type);
        $this->assertSame((string) $payment->id, $activity->auditable_id);
        $this->assertStringStartsWith('2026-07-01T09:00:00', $activity->new_values['paid_at']);
        $this->assertStringStartsWith('2026-08-05T12:00:00', $activity->new_values['created_at']);
    }

    public function test_backdated_badge_is_visible_in_payments_and_cash_journal(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('payments.store'), $this->payload('2026-07-01 09:00:00'))
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('saisi le 05/08');

        $this->actingAs($admin)
            ->get(route('accounting.cash-journal', [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-01',
            ]))
            ->assertOk()
            ->assertSee('saisi le 05/08');
    }

    private function createPaymentScenario(): void
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => 'Classe antidatage',
            'code' => 'PAY-DATE',
            'status' => 'active',
        ]);
        $feeType = FeeType::query()->create([
            'name' => 'Frais test antidatage',
            'code' => 'PAY-DATE',
            'is_required' => true,
            'status' => 'active',
        ]);
        $this->schedule = FeeSchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'fee_type_id' => $feeType->id,
            'amount' => 25_000,
            'period' => 'Test antidatage',
        ]);
        $this->student = Student::query()->create([
            'matricule' => 'LPP-PAY-DATE',
            'first_name' => 'Aminata',
            'last_name' => 'Sawadogo',
            'gender' => 'female',
            'status' => 'active',
        ]);

        Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $this->student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => now()->toDateString(),
            'type' => 'new',
            'status' => 'active',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(string $paidAt): array
    {
        return [
            'student_id' => $this->student->id,
            'payment_method' => 'cash',
            'paid_at' => $paidAt,
            'lines' => [[
                'fee_schedule_id' => $this->schedule->id,
                'amount' => 5_000,
            ]],
        ];
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'username' => $role.'-payment-date-'.uniqid(),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
