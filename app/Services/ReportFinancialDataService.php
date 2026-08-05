<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportFinancialDataService
{
    public function paymentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $expected = $this->expectedAmount($schoolClass, $academicYear);
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $validPayments = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->whereIn('student_id', $studentIds)
            ->where('status', 'valid')
            ->get();

        $paidByStudent = $validPayments
            ->groupBy('student_id')
            ->map(fn (Collection $payments) => (float) $payments->sum('amount'));

        return $schoolClass->enrollments
            ->map(function ($enrollment) use ($expected, $paidByStudent, $validPayments) {
                $paid = (float) ($paidByStudent[$enrollment->student_id] ?? 0);
                $balance = is_null($expected) ? null : max($expected - $paid, 0);
                $student = $enrollment->student;
                $lastPaymentAt = $validPayments
                    ->where('student_id', $enrollment->student_id)
                    ->sortByDesc('paid_at')
                    ->first()?->paid_at;

                return [
                    'enrollment' => $enrollment,
                    'student' => $student,
                    'expected' => $expected,
                    'paid' => $paid,
                    'balance' => $balance,
                    'progress' => $expected ? min((int) round(($paid / $expected) * 100), 100) : 0,
                    'contact' => $this->studentPaymentContact($student),
                    'last_payment_at' => $lastPaymentAt,
                    'status' => $this->paymentStatus($expected, $paid),
                ];
            })
            ->values();
    }

    public function filterPaymentRows(Collection $rows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();

        return $rows
            ->when($status, fn (Collection $items) => $items->filter(fn (array $row) => $row['status']['key'] === $status))
            ->when($search, function (Collection $items) use ($search) {
                return $items->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student?->full_name ?? ''), $search)
                        || Str::contains(Str::lower($student?->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    public function paymentSummary(Collection $rows): array
    {
        $expectedTotal = $rows->contains(fn (array $row) => is_null($row['expected']))
            ? null
            : $rows->sum('expected');

        $paidTotal = $rows->sum('paid');

        return [
            'expected' => $expectedTotal,
            'paid' => $paidTotal,
            'balance' => is_null($expectedTotal) ? null : max($expectedTotal - $paidTotal, 0),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }

    public function installmentRows(?SchoolClass $schoolClass, ?AcademicYear $academicYear): Collection
    {
        if (! $schoolClass) {
            return collect();
        }

        $schedules = FeeSchedule::query()
            ->with('feeType')
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('period')
            ->orderBy('id')
            ->get();
        $studentIds = $schoolClass->enrollments->pluck('student_id')->all();

        $paidByStudentAndSchedule = PaymentLine::query()
            ->join('payments', 'payments.id', '=', 'payment_lines.payment_id')
            ->when($academicYear, fn ($query) => $query->where('payments.academic_year_id', $academicYear->id))
            ->where('payments.status', 'valid')
            ->whereIn('payments.student_id', $studentIds)
            ->whereNotNull('payment_lines.fee_schedule_id')
            ->selectRaw('payments.student_id, payment_lines.fee_schedule_id, sum(payment_lines.amount) as total_paid')
            ->groupBy('payments.student_id', 'payment_lines.fee_schedule_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->student_id.':'.$row->fee_schedule_id => (float) $row->total_paid]);

        return $schoolClass->enrollments
            ->flatMap(function ($enrollment) use ($paidByStudentAndSchedule, $schedules) {
                return $schedules->map(function (FeeSchedule $schedule) use ($enrollment, $paidByStudentAndSchedule) {
                    $expected = (float) $schedule->amount;
                    $paid = (float) ($paidByStudentAndSchedule[$enrollment->student_id.':'.$schedule->id] ?? 0);
                    $balance = max($expected - $paid, 0);

                    return [
                        'student' => $enrollment->student,
                        'schedule' => $schedule,
                        'expected' => $expected,
                        'paid' => $paid,
                        'balance' => $balance,
                        'status' => $this->paymentStatus($expected, $paid),
                    ];
                });
            })
            ->values();
    }

    public function installmentSummary(Collection $rows): array
    {
        return [
            'expected' => (float) $rows->sum('expected'),
            'paid' => (float) $rows->sum('paid'),
            'balance' => (float) $rows->sum('balance'),
            'up_to_date' => $rows->filter(fn (array $row) => $row['status']['label'] === 'A jour')->count(),
            'partial' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Partiel')->count(),
            'unpaid' => $rows->filter(fn (array $row) => $row['status']['label'] === 'Impaye')->count(),
        ];
    }

    public function installmentStudentRows(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row) => filled($row['student']?->id))
            ->groupBy(fn (array $row) => $row['student']->id)
            ->map(function (Collection $studentInstallments) {
                $student = $studentInstallments->first()['student'];
                $expected = (float) $studentInstallments->sum('expected');
                $paid = (float) $studentInstallments->sum('paid');
                $balance = (float) $studentInstallments->sum('balance');
                $unpaidRows = $studentInstallments
                    ->filter(fn (array $row) => (float) $row['balance'] > 0)
                    ->values();
                $status = $this->studentInstallmentStatus($expected, $paid, $balance);

                return [
                    'student' => $student,
                    'expected' => $expected,
                    'paid' => $paid,
                    'balance' => $balance,
                    'progress' => $expected > 0 ? min(round(($paid / $expected) * 100), 100) : 0,
                    'status' => $status,
                    'rows' => $studentInstallments->values(),
                    'unpaid_rows' => $unpaidRows,
                    'unpaid_count' => $unpaidRows->count(),
                ];
            })
            ->sortBy(function (array $row) {
                $order = ['unpaid' => 0, 'partial' => 1, 'paid' => 2];

                return ($order[$row['status']['key']] ?? 9).'|'.Str::lower($row['student']->full_name);
            })
            ->values();
    }

    public function filterInstallmentStudentRows(Collection $studentRows, Request $request): Collection
    {
        $search = Str::lower(trim($request->string('search')->toString()));
        $status = $request->string('status')->toString();

        return $studentRows
            ->when($status, fn (Collection $rows) => $rows->filter(fn (array $row) => $row['status']['key'] === $status))
            ->when($search, function (Collection $rows) use ($search) {
                return $rows->filter(function (array $row) use ($search) {
                    $student = $row['student'];

                    return Str::contains(Str::lower($student->full_name), $search)
                        || Str::contains(Str::lower($student->matricule ?? ''), $search);
                });
            })
            ->values();
    }

    public function installmentStudentSummary(Collection $studentRows): array
    {
        return [
            'total' => $studentRows->count(),
            'paid' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'paid')->count(),
            'partial' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'partial')->count(),
            'unpaid' => $studentRows->filter(fn (array $row) => $row['status']['key'] === 'unpaid')->count(),
        ];
    }

    private function expectedAmount(SchoolClass $schoolClass, ?AcademicYear $academicYear): ?float
    {
        $amount = FeeSchedule::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('school_class_id', $schoolClass->id)
            ->sum('amount');

        return $amount > 0 ? (float) $amount : null;
    }

    private function paymentStatus(?float $expected, float $paid): array
    {
        if (is_null($expected)) {
            return ['key' => 'unconfigured', 'label' => 'Tarif à configurer', 'class' => 'badge-warning'];
        }

        if ($paid <= 0) {
            return ['key' => 'unpaid', 'label' => 'Impaye', 'class' => 'badge-warning'];
        }

        if ($paid < $expected) {
            return ['key' => 'partial', 'label' => 'Partiel', 'class' => 'badge-warning'];
        }

        return ['key' => 'paid', 'label' => 'A jour', 'class' => ''];
    }

    private function studentPaymentContact(?Student $student): string
    {
        if (! $student) {
            return '';
        }

        $guardian = $student->guardians->first();

        return $guardian?->phone_primary
            ?? $guardian?->phone_secondary
            ?? $student->home_phone
            ?? $student->emergency_contact_phone
            ?? '';
    }

    private function studentInstallmentStatus(float $expected, float $paid, float $balance): array
    {
        if ($expected <= 0) {
            return ['key' => 'unpaid', 'label' => 'Tarif à configurer', 'class' => 'badge-warning'];
        }

        if ($balance <= 0) {
            return ['key' => 'paid', 'label' => 'A jour', 'class' => ''];
        }

        if ($paid > 0) {
            return ['key' => 'partial', 'label' => 'Partiel', 'class' => 'badge-warning'];
        }

        return ['key' => 'unpaid', 'label' => 'Impaye', 'class' => 'badge-danger'];
    }
}
