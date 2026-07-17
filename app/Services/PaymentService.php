<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly ReceiptNumberService $receiptNumberService
    ) {
    }

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

        return DB::transaction(function () use ($student, $academicYear, $receiver, $lines, $data) {
            $amount = collect($lines)->sum(fn (array $line) => (float) $line['amount']);
            $enrollment = Enrollment::query()
                ->where('academic_year_id', $academicYear->id)
                ->where('student_id', $student->id)
                ->first();

            $payment = Payment::create([
                'receipt_number' => $this->receiptNumberService->generate(),
                'academic_year_id' => $academicYear->id,
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
                    'amount' => $line['amount'],
                ]);
            }

            return $payment->load(['student', 'lines.feeType', 'receiver']);
        });
    }

    public function cancel(Payment $payment, User $user, string $reason): Payment
    {
        if ($payment->status === 'cancelled') {
            return $payment;
        }

        $payment->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason . ' | Annule par: ' . $user->name,
        ])->save();

        return $payment;
    }
}
