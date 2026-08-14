<?php

namespace Tests\Feature;

use App\Jobs\SendCommunicationEmail;
use App\Mail\BusinessNotificationMail;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationMessage;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceSessionService;
use App\Services\CommunicationQuotaService;
use App\Services\CommunicationService;
use App\Services\PaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BusinessNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_an_announcement_for_real_guardian_addresses_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $admin = $this->userWithRole('admin', 'notification-admin@lyceepagnidibsom.com');
        [$student] = $this->schoolScenario();
        $this->attachGuardian($student, 'parent.reel@gmail.com');
        $this->attachGuardian($student, 'parent.demo@example.com', 'Demo', 'other', false);

        $this->actingAs($admin)
            ->post(route('communications.announcements.store'), [
                'title' => 'Réunion des parents',
                'audience' => 'guardians_all',
                'subject' => 'Réunion importante',
                'body' => "Bonjour {{ recipient_name }},\nUne réunion est prévue.",
            ])
            ->assertRedirect(route('communications.index', ['tab' => 'history']));

        $campaign = CommunicationCampaign::query()->firstOrFail();

        $this->assertSame(1, $campaign->recipients_count);
        $this->assertDatabaseHas('communication_messages', [
            'campaign_id' => $campaign->id,
            'recipient_email' => 'parent.reel@gmail.com',
            'status' => 'queued',
        ]);
        $this->assertDatabaseMissing('communication_messages', [
            'recipient_email' => 'parent.demo@example.com',
        ]);
        Queue::assertPushed(SendCommunicationEmail::class, 1);
    }

    public function test_payment_creation_queues_a_receipt_email_without_blocking_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $receiver = $this->userWithRole('comptable', 'comptable@lyceepagnidibsom.com');
        [$student, $schoolClass, $academicYear, $enrollment] = $this->schoolScenario();
        $this->attachGuardian($student, 'paiement.parent@gmail.com');
        $feeType = FeeType::query()->create([
            'name' => 'Frais notification test',
            'code' => 'NOTIF-TEST',
            'is_required' => true,
            'status' => 'active',
        ]);
        $schedule = FeeSchedule::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'fee_type_id' => $feeType->id,
            'amount' => 15000,
            'period' => 'Test',
        ]);

        $payment = app(PaymentService::class)->createPayment(
            $student,
            $academicYear,
            $receiver,
            [[
                'fee_type_id' => $feeType->id,
                'fee_schedule_id' => $schedule->id,
                'amount' => 15000,
            ]],
            ['payment_method' => 'cash'],
        );

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'valid']);
        $this->assertDatabaseHas('communication_messages', [
            'event_type' => 'payment_received',
            'related_id' => $payment->id,
            'recipient_email' => 'paiement.parent@gmail.com',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendCommunicationEmail::class, 1);
    }

    public function test_attendance_alert_is_only_queued_when_absence_status_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $user = $this->userWithRole('surveillant', 'surveillance@lyceepagnidibsom.com');
        [$student, $schoolClass, $academicYear] = $this->schoolScenario();
        $this->attachGuardian($student, 'absence.parent@gmail.com');
        $session = AttendanceSession::query()->create([
            'academic_year_id' => $academicYear->id,
            'school_class_id' => $schoolClass->id,
            'session_date' => today(),
            'created_by' => $user->id,
        ]);
        $service = app(AttendanceSessionService::class);
        $record = [[
            'student_id' => $student->id,
            'status' => 'absent',
            'minutes_late' => null,
            'reason' => null,
        ]];

        $service->updateRecords($session, $record, $user);
        $service->updateRecords($session, $record, $user);

        $this->assertDatabaseCount('communication_messages', 1);
        Queue::assertPushed(SendCommunicationEmail::class, 1);

        $record[0]['status'] = 'late';
        $record[0]['minutes_late'] = 15;
        $service->updateRecords($session, $record, $user);

        $this->assertDatabaseCount('communication_messages', 2);
        Queue::assertPushed(SendCommunicationEmail::class, 2);
    }

    public function test_student_status_change_queues_a_message_for_the_guardian(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        $secretariat = $this->userWithRole('secretariat', 'statut@lyceepagnidibsom.com');
        [$student] = $this->schoolScenario();
        $this->attachGuardian($student, 'statut.parent@gmail.com');

        $this->actingAs($secretariat)
            ->put(route('students.update', $student), [
                'status' => 'transferred',
            ])
            ->assertRedirect(route('students.show', $student));

        $this->assertSame('transferred', $student->refresh()->status);
        $this->assertDatabaseHas('communication_messages', [
            'event_type' => 'student_status_changed',
            'related_id' => $student->id,
            'recipient_email' => 'statut.parent@gmail.com',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendCommunicationEmail::class, 1);
    }

    public function test_job_sends_safe_html_and_marks_the_message_as_sent(): void
    {
        $this->seed(DatabaseSeeder::class);
        Mail::fake();
        $message = CommunicationMessage::query()->create([
            'event_type' => 'announcement',
            'recipient_name' => 'Parent Test',
            'recipient_email' => 'parent.test@gmail.com',
            'subject' => 'Information',
            'body' => '<script>alert("test")</script>',
            'status' => 'queued',
        ]);
        $job = new SendCommunicationEmail($message->id);

        $job->handle(
            app(CommunicationQuotaService::class),
            app(CommunicationService::class),
        );

        $this->assertSame('sent', $message->refresh()->status);
        Mail::assertSent(BusinessNotificationMail::class, function (BusinessNotificationMail $mail) {
            $html = $mail->render();

            return str_contains($html, '&lt;script&gt;')
                && ! str_contains($html, '<script>alert');
        });
    }

    public function test_quota_service_protects_the_daily_reserve(): void
    {
        $this->seed(DatabaseSeeder::class);
        config()->set('communication.quota.daily', 2);
        config()->set('communication.quota.daily_reserve', 1);
        CommunicationMessage::query()->create([
            'event_type' => 'announcement',
            'recipient_name' => 'Déjà envoyé',
            'recipient_email' => 'sent@gmail.com',
            'subject' => 'Déjà envoyé',
            'body' => 'Message',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $availability = app(CommunicationQuotaService::class)->availability();

        $this->assertFalse($availability['available']);
        $this->assertSame('Quota quotidien Resend atteint ou réserve quotidienne protégée.', $availability['reason']);
    }

    public function test_communication_permissions_follow_the_role_matrix(): void
    {
        $this->seed(DatabaseSeeder::class);
        $secretariat = $this->userWithRole('secretariat', 'secretariat@lyceepagnidibsom.com');
        $comptable = $this->userWithRole('comptable', 'compta@lyceepagnidibsom.com');
        $surveillant = $this->userWithRole('surveillant', 'surveillant@lyceepagnidibsom.com');

        $this->actingAs($secretariat)->get(route('communications.index'))->assertOk();
        $this->actingAs($comptable)->get(route('communications.index'))->assertOk();
        $this->actingAs($comptable)
            ->post(route('communications.announcements.store'), [])
            ->assertForbidden();
        $this->actingAs($surveillant)->get(route('communications.index'))->assertForbidden();
    }

    private function schoolScenario(): array
    {
        $academicYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $schoolClass = SchoolClass::query()->create([
            'academic_year_id' => $academicYear->id,
            'level_id' => Level::query()->firstOrFail()->id,
            'name' => 'Classe notifications '.uniqid(),
            'code' => 'NOT-'.uniqid(),
            'status' => 'active',
        ]);
        $student = Student::query()->create([
            'matricule' => 'LPP-NOT-'.uniqid(),
            'first_name' => 'Aïcha',
            'last_name' => 'Test',
            'gender' => 'female',
            'status' => 'active',
        ]);
        $enrollment = Enrollment::query()->create([
            'academic_year_id' => $academicYear->id,
            'student_id' => $student->id,
            'school_class_id' => $schoolClass->id,
            'enrollment_date' => today(),
            'type' => 'new',
            'status' => 'active',
        ]);

        return [$student, $schoolClass, $academicYear, $enrollment];
    }

    private function attachGuardian(
        Student $student,
        string $email,
        string $lastName = 'Parent',
        string $relationship = 'tutor',
        bool $isPrimary = true,
    ): Guardian {
        $guardian = Guardian::query()->create([
            'first_name' => 'Mariam',
            'last_name' => $lastName,
            'phone_primary' => '70'.random_int(100000, 999999),
            'email' => $email,
            'status' => 'active',
        ]);
        $student->guardians()->attach($guardian->id, [
            'relationship' => $relationship,
            'is_primary' => $isPrimary,
            'can_receive_sms' => true,
            'can_pickup_child' => true,
        ]);

        return $guardian;
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'username' => $role.'-notification-test-'.uniqid(),
            'email' => $email,
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
