<?php

namespace App\Services;

use App\Jobs\SendCommunicationEmail;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationMessage;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CommunicationService
{
    public function __construct(
        private readonly CommunicationTemplateService $templates,
        private readonly CommunicationRecipientService $recipients,
    ) {}

    public function queuePayment(Payment $payment, ?User $createdBy = null): void
    {
        $this->withoutBlockingBusinessFlow(function () use ($payment, $createdBy) {
            $payment->loadMissing(['student.guardians', 'enrollment.schoolClass']);
            $student = $payment->student;

            if (! $student) {
                return;
            }

            $baseVariables = [
                'student_name' => $student->full_name,
                'amount' => number_format((float) $payment->amount, 0, ',', ' '),
                'payment_date' => $payment->paid_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'receipt_number' => $payment->receipt_number,
                'class_name' => $payment->enrollment?->schoolClass?->name ?? 'Non renseignée',
            ];

            $this->queueAutomaticMessages(
                'payment_received',
                $payment,
                $this->recipients->guardiansForStudent($student),
                $baseVariables,
                $createdBy,
                fn (array $recipient) => hash('sha256', "payment:{$payment->id}:{$recipient['email']}"),
            );
        }, 'payment_received', $payment->id);
    }

    public function queueAttendance(AttendanceRecord $record, ?User $createdBy = null): void
    {
        if (! in_array($record->status, ['absent', 'late'], true)) {
            return;
        }

        $this->withoutBlockingBusinessFlow(function () use ($record, $createdBy) {
            $record->loadMissing(['student.guardians', 'session.schoolClass']);
            $student = $record->student;

            if (! $student || ! $record->session) {
                return;
            }

            $baseVariables = [
                'student_name' => $student->full_name,
                'attendance_status' => $record->status === 'late' ? 'en retard' : 'absent(e)',
                'attendance_date' => $record->session->session_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'class_name' => $record->session->schoolClass?->name ?? 'Non renseignée',
                'minutes_late_line' => $record->status === 'late' && $record->minutes_late
                    ? "\nRetard : {$record->minutes_late} minute(s)."
                    : '',
                'reason_line' => $record->reason ? "\nMotif indiqué : {$record->reason}" : '',
            ];

            $this->queueAutomaticMessages(
                'attendance_alert',
                $record,
                $this->recipients->guardiansForStudent($student),
                $baseVariables,
                $createdBy,
                fn (array $recipient) => hash('sha256', "attendance:{$record->id}:{$record->status}:{$recipient['email']}"),
            );
        }, 'attendance_alert', $record->id);
    }

    public function queueStudentStatusChange(Student $student, string $oldStatus, string $newStatus, ?User $createdBy = null): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $this->withoutBlockingBusinessFlow(function () use ($student, $oldStatus, $newStatus, $createdBy) {
            $student->loadMissing('guardians');
            $changedAt = now();
            $baseVariables = [
                'student_name' => $student->full_name,
                'old_status' => $this->studentStatusLabel($oldStatus),
                'new_status' => $this->studentStatusLabel($newStatus),
                'changed_at' => $changedAt->format('d/m/Y H:i'),
            ];

            $this->queueAutomaticMessages(
                'student_status_changed',
                $student,
                $this->recipients->guardiansForStudent($student),
                $baseVariables,
                $createdBy,
                fn (array $recipient) => hash('sha256', implode(':', [
                    'student-status',
                    $student->id,
                    $oldStatus,
                    $newStatus,
                    $student->updated_at?->format('Y-m-d-H-i-s-u') ?? $changedAt->format('Y-m-d-H-i-s-u'),
                    $recipient['email'],
                ])),
            );
        }, 'student_status_changed', $student->id);
    }

    public function createAnnouncement(User $user, ?AcademicYear $academicYear, array $data): CommunicationCampaign
    {
        $recipients = match ($data['audience']) {
            'guardians_all' => $this->recipients->guardians($academicYear),
            'guardians_class' => $this->recipients->guardians($academicYear, (int) $data['school_class_id']),
            'staff_all' => $this->recipients->staff(),
            'staff_role' => $this->recipients->staff($data['role_name']),
        };

        $campaign = DB::transaction(function () use ($user, $data, $recipients) {
            $campaign = CommunicationCampaign::query()->create([
                'type' => 'announcement',
                'audience' => $data['audience'],
                'school_class_id' => $data['school_class_id'] ?? null,
                'role_name' => $data['role_name'] ?? null,
                'title' => $data['title'],
                'subject' => $this->templates->sanitizeSubject($data['subject']),
                'body' => $data['body'],
                'status' => $recipients->isEmpty() ? 'completed' : 'queued',
                'recipients_count' => $recipients->count(),
                'created_by' => $user->id,
                'queued_at' => $recipients->isEmpty() ? null : now(),
                'completed_at' => $recipients->isEmpty() ? now() : null,
            ]);

            foreach ($recipients as $recipient) {
                $variables = ['recipient_name' => $recipient['name']];
                $campaign->messages()->create([
                    'event_type' => 'announcement',
                    'recipient_type' => $recipient['type'],
                    'recipient_id' => $recipient['id'],
                    'recipient_name' => $recipient['name'],
                    'recipient_email' => $recipient['email'],
                    'subject' => $this->templates->sanitizeSubject(
                        $this->templates->replaceVariables($data['subject'], $variables),
                    ),
                    'body' => $this->templates->replaceVariables($data['body'], $variables),
                    'status' => 'pending',
                    'created_by' => $user->id,
                ]);
            }

            return $campaign;
        });

        $campaign->messages()->pluck('id')->each(fn (int $messageId) => $this->dispatchMessage($messageId));

        return $campaign->refresh();
    }

    public function retry(CommunicationMessage $message): void
    {
        if ($message->status === 'sent') {
            return;
        }

        $message->forceFill([
            'status' => 'pending',
            'error_message' => null,
            'failed_at' => null,
            'queued_at' => null,
        ])->save();

        $this->dispatchMessage($message->id);
    }

    public function refreshCampaign(?CommunicationCampaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        $counts = $campaign->messages()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $skipped = (int) ($counts['skipped'] ?? 0);
        $waiting = collect(['pending', 'queued', 'deferred'])
            ->sum(fn (string $status) => (int) ($counts[$status] ?? 0));

        $status = $waiting > 0
            ? 'processing'
            : ($failed === 0 ? 'completed' : ($sent > 0 ? 'partial' : 'failed'));

        $campaign->forceFill([
            'status' => $status,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'completed_at' => $waiting === 0 ? now() : null,
        ])->save();
    }

    private function queueAutomaticMessages(
        string $templateCode,
        Model $related,
        Collection $recipients,
        array $baseVariables,
        ?User $createdBy,
        callable $deduplicationKey,
    ): void {
        foreach ($recipients as $recipient) {
            $content = $this->templates->render($templateCode, $baseVariables + [
                'recipient_name' => $recipient['name'],
            ]);

            if (! $content) {
                continue;
            }

            $message = CommunicationMessage::query()->firstOrCreate(
                ['deduplication_key' => $deduplicationKey($recipient)],
                [
                    'template_code' => $templateCode,
                    'event_type' => $templateCode,
                    'related_type' => $related->getMorphClass(),
                    'related_id' => $related->getKey(),
                    'recipient_type' => $recipient['type'],
                    'recipient_id' => $recipient['id'],
                    'recipient_name' => $recipient['name'],
                    'recipient_email' => $recipient['email'],
                    'subject' => $content['subject'],
                    'body' => $content['body'],
                    'status' => 'pending',
                    'metadata' => $baseVariables,
                    'created_by' => $createdBy?->id,
                ],
            );

            if ($message->wasRecentlyCreated) {
                $this->dispatchMessage($message->id);
            }
        }
    }

    private function dispatchMessage(int $messageId): void
    {
        CommunicationMessage::query()->whereKey($messageId)->update([
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        SendCommunicationEmail::dispatch($messageId);
    }

    private function studentStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Actif',
            'transferred' => 'Transféré',
            'dropped' => 'Abandon',
            'graduated' => 'Diplômé',
            'suspended' => 'Suspendu',
            default => Str::headline($status),
        };
    }

    private function withoutBlockingBusinessFlow(callable $callback, string $event, int|string $relatedId): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::error('Échec de préparation d’une notification métier.', [
                'event' => $event,
                'related_id' => $relatedId,
                'exception' => $exception,
            ]);
        }
    }
}
