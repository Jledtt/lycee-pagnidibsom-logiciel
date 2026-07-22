<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\CancelPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\AcademicYear;
use App\Models\FeeSchedule;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\PaymentFinancialProfileService;
use App\Services\PaymentService;
use App\Services\XlsxExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentWebController extends Controller
{
    public function __construct(
        private readonly PaymentFinancialProfileService $financialProfileService,
    ) {}

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
        $students = $this->financialProfileService->enrolledStudents($academicYear);

        return view('payments.create', [
            'academicYear' => $academicYear,
            'payment' => new Payment([
                'payment_method' => 'cash',
                'paid_at' => now(),
                'status' => 'valid',
            ]),
            'students' => $students,
            'feeTypes' => FeeType::query()->where('status', 'active')->orderBy('name')->get(),
            'paymentProfiles' => $this->financialProfileService->paymentProfiles($students, $academicYear),
            'prefillAmount' => $request->integer('amount') > 0 ? $request->integer('amount') : null,
            'prefillFeeScheduleId' => $request->integer('fee_schedule_id') ?: null,
            'selectedStudentId' => $request->integer('student_id'),
        ]);
    }

    public function export(Request $request, XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $payments = $this->paymentQuery($request, $academicYear)
            ->orderByDesc('paid_at')
            ->get();

        return $xlsxExport->download('paiements-'.now()->format('Ymd-His').'.xlsx', [
            'Reçu',
            'Date',
            'Élève',
            'Matricule',
            'Classe',
            'Frais',
            'Tranche',
            'Montant ligne',
            'Montant reçu',
            'Mode',
            'Statut',
            'Encaisse par',
            'Date annulation',
            'Motif annulation',
            'Notes',
        ], $payments->flatMap(function (Payment $payment) {
            $lines = $payment->lines->isNotEmpty() ? $payment->lines : collect([null]);

            return $lines->map(fn ($line) => [
                $payment->receipt_number,
                $payment->paid_at?->format('d/m/Y H:i'),
                $payment->student?->full_name,
                $payment->student?->matricule,
                $payment->enrollment?->schoolClass?->name,
                $line?->feeType?->name ?? '',
                $line?->feeSchedule?->period ?? '',
                $line ? (float) $line->amount : (float) $payment->amount,
                (float) $payment->amount,
                $payment->payment_method,
                $payment->status,
                $payment->receiver?->name,
                $payment->cancelled_at?->format('d/m/Y H:i'),
                $payment->cancellation_reason,
                $payment->notes,
            ]);
        }));
    }

    public function store(StorePaymentRequest $request, PaymentService $paymentService): RedirectResponse
    {
        $academicYear = $this->requireActiveAcademicYear();
        $data = $request->validated();

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
            ->with('success', 'Paiement enregistré avec succès.');
    }

    public function show(Payment $payment): View
    {
        $payment->load(['student.guardians', 'academicYear', 'enrollment.schoolClass.level', 'lines.feeType', 'lines.feeSchedule', 'receiver']);

        return view('payments.show', [
            'academicYear' => $this->activeAcademicYear(),
            'payment' => $payment,
            'summary' => $this->financialProfileService->studentPaymentSummary($payment->student, $payment->academicYear),
        ]);
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['student.guardians', 'academicYear', 'enrollment.schoolClass.level', 'lines.feeType', 'lines.feeSchedule', 'receiver']);
        $filename = 'recu-'.Str::slug($payment->receipt_number.'-'.$payment->student->full_name).'.pdf';

        return Pdf::loadView('payments.receipt-pdf', [
            'payment' => $payment,
            'summary' => $this->financialProfileService->studentPaymentSummary($payment->student, $payment->academicYear),
        ])
            ->setPaper('a5', 'landscape')
            ->stream($filename);
    }

    public function studentStatement(Student $student): View
    {
        $academicYear = $this->activeAcademicYear();

        return view('payments.student-statement', [
            'academicYear' => $academicYear,
            'profile' => $this->financialProfileService->studentFinancialProfile($student, $academicYear),
            'student' => $student->load('guardians'),
        ]);
    }

    public function studentStatementPdf(Student $student)
    {
        $academicYear = $this->activeAcademicYear();
        $filename = 'situation-financiere-'.Str::slug($student->matricule.'-'.$student->full_name).'.pdf';

        return Pdf::loadView('payments.student-statement-pdf', [
            'academicYear' => $academicYear,
            'profile' => $this->financialProfileService->studentFinancialProfile($student, $academicYear),
            'school' => SchoolSetting::query()->first(),
            'student' => $student->load('guardians'),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    public function unpaid(): View
    {
        $academicYear = $this->activeAcademicYear();

        $rows = $this->financialProfileService->unpaidRows($academicYear);

        return view('payments.unpaid', [
            'academicYear' => $academicYear,
            'rows' => $rows,
        ]);
    }

    public function unpaidExport(XlsxExportService $xlsxExport)
    {
        $academicYear = $this->activeAcademicYear();
        $rows = $this->financialProfileService->unpaidRows($academicYear);

        return $xlsxExport->download('impayes-'.now()->format('Ymd-His').'.xlsx', [
            'Matricule',
            'Élève',
            'Classe',
            'Attendu',
            'Paye',
            'Reste',
            'Contact',
        ], $rows->map(function (array $row) {
            $student = $row['enrollment']->student;
            $guardian = $student->guardians->first();
            $summary = $row['summary'];

            return [
                $student->matricule,
                $student->full_name,
                $row['enrollment']->schoolClass?->name,
                $summary['expected'] ?? 'À configurer',
                $summary['paid'],
                $summary['balance'] ?? 'À configurer',
                $guardian?->phone_primary ?? $student->home_phone,
            ];
        }));
    }

    public function destroy(CancelPaymentRequest $request, Payment $payment, PaymentService $paymentService): RedirectResponse
    {
        $data = $request->validated();

        $paymentService->cancel($payment, $request->user(), $data['reason']);

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', 'Paiement annulé.');
    }

    private function activeAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    private function paymentQuery(Request $request, ?AcademicYear $academicYear)
    {
        return Payment::query()
            ->with(['student', 'enrollment.schoolClass', 'lines.feeType', 'lines.feeSchedule', 'receiver'])
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
            });
    }

    private function requireActiveAcademicYear(): AcademicYear
    {
        $academicYear = $this->activeAcademicYear();

        abort_if(! $academicYear, 422, 'Aucune année scolaire active.');

        return $academicYear;
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
}
