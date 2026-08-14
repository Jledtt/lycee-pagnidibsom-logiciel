<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly ReceiptNumberService $receiptNumberService,
        private readonly CommunicationService $communicationService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function createPayment(
        Student $student,
        AcademicYear $academicYear,
        User $receiver,
        array $lines,
        array $data = []
    ): Payment {
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Le paiement doit contenir au moins une ligne.',
            ]);
        }

        foreach ($lines as $index => $line) {
            $rawAmount = $line['amount'] ?? null;
            $isInteger = is_int($rawAmount)
                || (is_string($rawAmount) && ctype_digit($rawAmount));

            if (! $isInteger || (int) $rawAmount <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Le montant doit être un nombre entier strictement positif.',
                ]);
            }

            if ((int) ($line['fee_type_id'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.fee_type_id" => 'Le type de frais est obligatoire.',
                ]);
            }
        }

        $payment = DB::transaction(function () use ($student, $academicYear, $receiver, $lines, $data) {
            $amount = collect($lines)->sum(fn (array $line): int => (int) $line['amount']);
            $lockedAcademicYear = AcademicYear::query()
                ->lockForUpdate()
                ->findOrFail($academicYear->id);

            if (! $lockedAcademicYear->is_active) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'Cette année scolaire n’est plus active.',
                ]);
            }

            $enrollment = Enrollment::query()
                ->where('academic_year_id', $lockedAcademicYear->id)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'student_id' => 'Cet élève n’est pas inscrit pour l’année scolaire active.',
                ]);
            }

            $scheduleIds = collect($lines)
                ->pluck('fee_schedule_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $schedules = FeeSchedule::query()
                ->whereIn('id', $scheduleIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $feeTypeIds = collect($lines)
                ->pluck('fee_type_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
            $knownFeeTypeIds = FeeType::query()
                ->whereIn('id', $feeTypeIds)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            foreach ($lines as $index => $line) {
                $scheduleId = (int) ($line['fee_schedule_id'] ?? 0);

                if (! $knownFeeTypeIds->contains((int) $line['fee_type_id'])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.fee_type_id" => 'Le type de frais sélectionné n’existe plus.',
                    ]);
                }

                if (! $scheduleId) {
                    continue;
                }

                $schedule = $schedules->get($scheduleId);

                if (! $schedule
                    || (int) $schedule->academic_year_id !== (int) $lockedAcademicYear->id
                    || (int) $schedule->school_class_id !== (int) $enrollment->school_class_id) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.fee_schedule_id" => 'Cet échéancier ne correspond pas à l’année et à la classe de l’élève.',
                    ]);
                }

                if ((int) $schedule->fee_type_id !== (int) ($line['fee_type_id'] ?? 0)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.fee_type_id" => 'Le type de frais ne correspond pas à l’échéancier choisi.',
                    ]);
                }
            }

            $payment = Payment::create([
                'receipt_number' => $this->receiptNumberService->generate(),
                'academic_year_id' => $lockedAcademicYear->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id,
                'paid_at' => $data['paid_at'] ?? now(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'status' => 'valid',
                'received_by' => $receiver->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $payment->lines()->create([
                    'fee_type_id' => $line['fee_type_id'],
                    'fee_schedule_id' => $line['fee_schedule_id'] ?? null,
                    'amount' => (int) $line['amount'],
                ]);
            }

            return $payment->load(['student', 'lines.feeType', 'receiver']);
        });

        if ($payment->isBackdated()) {
            $this->auditTrailService->record(
                'payment.backdated',
                $payment,
                [],
                [
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'created_at' => $payment->created_at->toIso8601String(),
                ],
                'Paiement antidaté : '.$payment->receipt_number,
            );
        }

        $this->communicationService->queuePayment($payment, $receiver);

        return $payment;
    }

    public function cancel(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason): Payment {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($lockedPayment->status !== 'cancelled') {
                $lockedPayment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $user->id,
                    'cancellation_reason' => trim($reason),
                ]);
            }

            return $lockedPayment->load('canceller');
        });
    }
}
