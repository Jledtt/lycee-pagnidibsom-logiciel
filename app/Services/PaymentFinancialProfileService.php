<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\Student;
use Illuminate\Support\Collection;

class PaymentFinancialProfileService
{
    public function paymentFormData(
        ?AcademicYear $academicYear,
        ?Student $selectedStudent = null,
        ?int $selectedScheduleId = null,
        ?int $selectedAmount = null,
    ): array {
        $students = $this->enrolledStudents($academicYear);

        return [
            'students' => $students,
            'feeTypes' => FeeType::query()->where('status', 'active')->orderBy('name')->get(),
            'profiles' => $this->paymentProfiles($students, $academicYear),
            'selectedStudentId' => $selectedStudent?->id,
            'selectedScheduleId' => $selectedScheduleId,
            'selectedAmount' => $selectedAmount,
        ];
    }

    public function enrolledStudents(?AcademicYear $academicYear): Collection
    {
        return Student::query()
            ->with('enrollments.schoolClass')
            ->where('status', 'active')
            ->whereHas('enrollments', function ($query) use ($academicYear) {
                $query->when($academicYear, fn ($subQuery) => $subQuery->where('academic_year_id', $academicYear->id))
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function paymentProfiles(Collection $students, ?AcademicYear $academicYear): array
    {
        $studentIds = $students->pluck('id')->all();
        $paidByStudentAndSchedule = Payment::query()
            ->whereIn('student_id', $studentIds)
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->with('lines')
            ->get()
            ->flatMap(fn (Payment $payment) => $payment->lines
                ->filter(fn ($line) => filled($line->fee_schedule_id))
                ->map(fn ($line) => [
                    'student_id' => $payment->student_id,
                    'fee_schedule_id' => $line->fee_schedule_id,
                    'amount' => (float) $line->amount,
                ]))
            ->groupBy(fn (array $row) => $row['student_id'].':'.$row['fee_schedule_id'])
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $classIds = $students
            ->flatMap(fn (Student $student) => $student->enrollments->pluck('school_class_id'))
            ->filter()
            ->unique()
            ->values();

        $schedulesByClass = FeeSchedule::query()
            ->with(['feeType', 'schoolClass'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereIn('school_class_id', $classIds)
            ->orderBy('period')
            ->orderBy('id')
            ->get()
            ->groupBy('school_class_id');

        return $students->mapWithKeys(function (Student $student) use ($paidByStudentAndSchedule, $schedulesByClass) {
            $enrollment = $student->enrollments->sortByDesc('id')->first();
            $schedules = $enrollment
                ? ($schedulesByClass[$enrollment->school_class_id] ?? collect())
                : collect();

            return [
                $student->id => $schedules->map(function (FeeSchedule $schedule) use ($student, $paidByStudentAndSchedule) {
                    $paid = (float) ($paidByStudentAndSchedule[$student->id.':'.$schedule->id] ?? 0);
                    $amount = (float) $schedule->amount;

                    return [
                        'id' => $schedule->id,
                        'label' => trim(($schedule->period ?: 'Sans période').' - '.($schedule->feeType?->name ?? 'Frais')),
                        'amount' => $amount,
                        'paid' => $paid,
                        'remaining' => max($amount - $paid, 0),
                    ];
                })->values()->all(),
            ];
        })->all();
    }

    public function studentPaymentSummary(Student $student, ?AcademicYear $academicYear): array
    {
        $enrollment = Enrollment::query()
            ->with('schoolClass')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $expected = null;

        if ($enrollment) {
            $scheduledAmount = FeeSchedule::query()
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('school_class_id', $enrollment->school_class_id)
                ->sum('amount');

            $expected = $scheduledAmount > 0 ? (float) $scheduledAmount : null;
        }

        $paid = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->where('status', 'valid')
            ->sum('amount');

        return [
            'enrollment' => $enrollment,
            'expected' => $expected,
            'paid' => (float) $paid,
            'balance' => is_null($expected) ? null : max($expected - (float) $paid, 0),
        ];
    }

    public function unpaidRows(?AcademicYear $academicYear): Collection
    {
        return Enrollment::query()
            ->with(['student.guardians', 'schoolClass.level'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->get()
            ->map(fn (Enrollment $enrollment) => [
                'enrollment' => $enrollment,
                'summary' => $this->studentPaymentSummary($enrollment->student, $academicYear),
            ])
            ->filter(fn (array $row) => is_null($row['summary']['balance']) || $row['summary']['balance'] > 0)
            ->values();
    }

    public function studentFinancialProfile(Student $student, ?AcademicYear $academicYear): array
    {
        $summary = $this->studentPaymentSummary($student, $academicYear);
        $enrollment = $summary['enrollment'];

        $scheduledRows = collect();

        if ($enrollment) {
            $paidBySchedule = PaymentLine::query()
                ->selectRaw('fee_schedule_id, SUM(payment_lines.amount) as paid')
                ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
                ->where('payments.student_id', $student->id)
                ->when($academicYear, fn ($query) => $query->where('payments.academic_year_id', $academicYear->id))
                ->where('payments.status', 'valid')
                ->whereNotNull('payment_lines.fee_schedule_id')
                ->groupBy('fee_schedule_id')
                ->pluck('paid', 'fee_schedule_id');

            $scheduledRows = FeeSchedule::query()
                ->with('feeType')
                ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
                ->where('school_class_id', $enrollment->school_class_id)
                ->orderBy('due_date')
                ->orderBy('period')
                ->orderBy('id')
                ->get()
                ->map(function (FeeSchedule $schedule) use ($paidBySchedule) {
                    $expected = (float) $schedule->amount;
                    $paid = (float) ($paidBySchedule[$schedule->id] ?? 0);
                    $remaining = max($expected - $paid, 0);

                    return [
                        'schedule' => $schedule,
                        'expected' => $expected,
                        'paid' => $paid,
                        'remaining' => $remaining,
                        'status' => $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                    ];
                });
        }

        $otherLines = PaymentLine::query()
            ->with(['feeType', 'payment'])
            ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
            ->where('payments.student_id', $student->id)
            ->when($academicYear, fn ($query) => $query->where('payments.academic_year_id', $academicYear->id))
            ->where('payments.status', 'valid')
            ->whereNull('payment_lines.fee_schedule_id')
            ->select('payment_lines.*')
            ->latest('payments.paid_at')
            ->get();

        $payments = Payment::query()
            ->with(['lines.feeType', 'lines.feeSchedule', 'receiver'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('student_id', $student->id)
            ->latest('paid_at')
            ->get();

        return [
            ...$summary,
            'other_lines' => $otherLines,
            'payments' => $payments,
            'scheduled_rows' => $scheduledRows,
        ];
    }
}
