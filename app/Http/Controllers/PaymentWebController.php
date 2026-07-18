<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentLine;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentWebController extends Controller
{
    public function index(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();

        $payments = Payment::query()
            ->with(['student', 'enrollment.schoolClass', 'lines.feeType', 'receiver'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('matricule', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('paid_at')
            ->paginate(12)
            ->withQueryString();

        $totalPaid = Payment::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'valid')
            ->sum('amount');

        return view('payments.index', [
            'academicYear' => $academicYear,
            'payments' => $payments,
            'totalPaid' => $totalPaid,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $academicYear = $this->activeAcademicYear();
        $students = $this->enrolledStudents($academicYear);

        return view('payments.create', [
            'academicYear' => $academicYear,
            'payment' => new Payment([
                'payment_method' => 'cash',
                'paid_at' => now(),
                'status' => 'valid',
            ]),
            'students' => $students,
            'feeTypes' => FeeType::query()->where('status', 'active')->orderBy('name')->get(),
            'paymentProfiles' => $this->paymentProfiles($students, $academicYear),
            'prefillAmount' => $request->integer('amount') > 0 ? $request->integer('amount') : null,
            'prefillFeeScheduleId' => $request->integer('fee_schedule_id') ?: null,
            'selectedStudentId' => $request->integer('student_id'),
        ]);
    }

    public function store(Request $request, PaymentService $paymentService): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank_transfer,other'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'],
            'lines.*.fee_type_id' => ['nullable', 'exists:fee_types,id'],
            'lines.*.fee_schedule_id' => ['nullable', 'exists:fee_schedules,id'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $lines = $this->paymentLines($data['lines']);

        if ($lines->isEmpty()) {
            return back()
                ->withErrors(['lines' => 'Ajoute au moins une ligne de paiement avec un type de frais et un montant.'])
                ->withInput();
        }

        $payment = $paymentService->createPayment(
            Student::findOrFail($data['student_id']),
            $academicYear,
            $request->user(),
            $lines->all(),
            $data,
        );

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Paiement enregistre avec succes.');
    }

    public function show(Payment $payment): View
    {
        $payment->load(['student.guardians', 'academicYear', 'enrollment.schoolClass.level', 'lines.feeType', 'lines.feeSchedule', 'receiver']);

        return view('payments.show', [
            'academicYear' => $this->activeAcademicYear(),
            'payment' => $payment,
            'summary' => $this->studentPaymentSummary($payment->student, $payment->academicYear),
        ]);
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['student.guardians', 'academicYear', 'enrollment.schoolClass.level', 'lines.feeType', 'lines.feeSchedule', 'receiver']);
        $filename = 'recu-' . Str::slug($payment->receipt_number . '-' . $payment->student->full_name) . '.pdf';

        return Pdf::loadView('payments.receipt-pdf', [
            'payment' => $payment,
            'summary' => $this->studentPaymentSummary($payment->student, $payment->academicYear),
        ])
            ->setPaper('a5', 'landscape')
            ->stream($filename);
    }

    public function studentStatement(Student $student): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('payments.student-statement', [
            'academicYear' => $academicYear,
            'profile' => $this->studentFinancialProfile($student, $academicYear),
            'student' => $student->load('guardians'),
        ]);
    }

    public function studentStatementPdf(Student $student)
    {
        $academicYear = $this->activeAcademicYear();
        $filename = 'situation-financiere-' . Str::slug($student->matricule . '-' . $student->full_name) . '.pdf';

        return Pdf::loadView('payments.student-statement-pdf', [
            'academicYear' => $academicYear,
            'profile' => $this->studentFinancialProfile($student, $academicYear),
            'school' => SchoolSetting::query()->first(),
            'student' => $student->load('guardians'),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function unpaid(): View
    {
        $academicYear = $this->activeAcademicYear();

        $rows = Enrollment::query()
            ->with(['student.guardians', 'schoolClass.level'])
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->get()
            ->map(function (Enrollment $enrollment) use ($academicYear) {
                return [
                    'enrollment' => $enrollment,
                    'summary' => $this->studentPaymentSummary($enrollment->student, $academicYear),
                ];
            })
            ->filter(fn (array $row) => is_null($row['summary']['balance']) || $row['summary']['balance'] > 0)
            ->values();

        return view('payments.unpaid', [
            'academicYear' => $academicYear,
            'rows' => $rows,
        ]);
    }

    public function destroy(Request $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $paymentService->cancel($payment, $request->user(), $data['reason']);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Paiement annule.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune annee scolaire active.');

        return $academicYear;
    }

    private function enrolledStudents(?AcademicYear $academicYear)
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

    private function paymentLines(array $lines): Collection
    {
        return collect($lines)
            ->filter(fn (array $line) => (filled($line['fee_schedule_id'] ?? null) || filled($line['fee_type_id'] ?? null)) && filled($line['amount'] ?? null))
            ->map(function (array $line) {
                $schedule = filled($line['fee_schedule_id'] ?? null)
                    ? FeeSchedule::query()->find((int) $line['fee_schedule_id'])
                    : null;

                return [
                    'fee_type_id' => $schedule?->fee_type_id ?? (int) ($line['fee_type_id'] ?? 0),
                    'fee_schedule_id' => $schedule?->id,
                    'amount' => (float) $line['amount'],
                ];
            })
            ->values();
    }

    private function paymentProfiles($students, ?AcademicYear $academicYear): array
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
            ->groupBy(fn (array $row) => $row['student_id'] . ':' . $row['fee_schedule_id'])
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
                    $paid = (float) ($paidByStudentAndSchedule[$student->id . ':' . $schedule->id] ?? 0);
                    $amount = (float) $schedule->amount;

                    return [
                        'id' => $schedule->id,
                        'label' => trim(($schedule->period ?: 'Sans periode') . ' - ' . ($schedule->feeType?->name ?? 'Frais')),
                        'amount' => $amount,
                        'paid' => $paid,
                        'remaining' => max($amount - $paid, 0),
                    ];
                })->values()->all(),
            ];
        })->all();
    }

    private function studentPaymentSummary(Student $student, ?AcademicYear $academicYear): array
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

    private function studentFinancialProfile(Student $student, ?AcademicYear $academicYear): array
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
